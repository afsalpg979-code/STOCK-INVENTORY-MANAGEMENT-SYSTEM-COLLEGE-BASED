<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

$message = '';
$error = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = post('action');
        $conn->begin_transaction();

        if ($action === 'inward') {
            $name = post('item_name'); $code = strtoupper(post('product_code'));
            $qty = (int)post('quantity'); $price = (float)post('price');
            $brand = post('brand'); $branch = post('branch');
            $barcode = post('barcode'); $serial = post('serial_number'); $rfid = strtoupper(post('rfid_uid'));
            $supplier = post('supplier'); $invoice = post('invoice_no'); $by = post('received_by');
            if ($name === '' || $code === '' || $qty <= 0) fail('Item name, product code and positive quantity are required.');
            $s = $conn->prepare('INSERT INTO principal_stock(name,qty,price,brand,branch) VALUES(?,?,?,?,?)');
            $s->bind_param('sidss', $name,$qty,$price,$brand,$branch); $s->execute(); $stockId=$conn->insert_id; $s->close();
            $qr = 'INV:' . $code;
            $s=$conn->prepare('INSERT INTO inventory_products(product_code,barcode,serial_number,qr_value,rfid_uid,principal_stock_id) VALUES(?,?,?,?,?,?)');
            $s->bind_param('sssssi',$code,$barcode,$serial,$qr,$rfid,$stockId); $s->execute(); $productId=$conn->insert_id; $s->close();
            if ($rfid !== '') {
                $s=$conn->prepare("INSERT INTO rfid_tags(uid,item_name,item_code,department,quantity,status) VALUES(?,?,?,?,?,'active') ON DUPLICATE KEY UPDATE item_name=VALUES(item_name),item_code=VALUES(item_code),quantity=VALUES(quantity),status='active',stock_zone='BOH'");
                $department=$branch; $s->bind_param('ssssi',$rfid,$name,$code,$department,$qty); $s->execute(); $s->close();
            }
            $s=$conn->prepare('INSERT INTO inventory_inward(product_id,product_code,item_name,quantity,price,brand,branch,supplier,invoice_no,received_by) VALUES(?,?,?,?,?,?,?,?,?,?)');
            $s->bind_param('issidsssss',$productId,$code,$name,$qty,$price,$brand,$branch,$supplier,$invoice,$by); $s->execute(); $s->close();
            $message='Stock IN completed and product identifiers registered.';
        }

        elseif ($action === 'movement' || $action === 'replenish') {
            $code=strtoupper(post('product_code')); $dept=post('department'); $qty=(int)post('quantity'); $by=post('requested_by');
            if ($code==='' || $dept==='' || $qty<=0) fail('Product code, department and positive quantity are required.');
            $s=$conn->prepare('SELECT p.id,p.product_code,p.principal_stock_id,s.name,s.brand,s.price,s.qty FROM inventory_products p JOIN principal_stock s ON s.id=p.principal_stock_id WHERE p.product_code=? FOR UPDATE');
            $s->bind_param('s',$code); $s->execute(); $r=$s->get_result()->fetch_assoc(); $s->close();
            if(!$r) fail('Product is not mapped to Principal Stock.');
            if((int)$r['qty']<$qty) fail('Insufficient Principal Stock. Available: '.$r['qty']);
            $type=$action==='replenish'?'REPLENISHMENT':'MOVEMENT';
            if($action==='replenish') {
                $s=$conn->prepare("INSERT INTO inventory_replenishments(product_id,product_code,item_name,department,current_quantity,requested_quantity,status,requested_by) VALUES(?,?,?,?,0,?,'FULFILLED',?)");
                $s->bind_param('isssis',$r['id'],$code,$r['name'],$dept,$qty,$by); $s->execute(); $repId=$conn->insert_id; $s->close();
            }
            $s=$conn->prepare('SELECT id,qty FROM hod_stock WHERE name=? AND brand=? AND branch=? LIMIT 1 FOR UPDATE');
            $s->bind_param('sss',$r['name'],$r['brand'],$dept); $s->execute(); $hod=$s->get_result()->fetch_assoc(); $s->close();
            if($hod){ $newQty=(int)$hod['qty']+$qty; $s=$conn->prepare('UPDATE hod_stock SET qty=? WHERE id=?'); $s->bind_param('ii',$newQty,$hod['id']); $s->execute(); $toId=$hod['id']; $s->close(); }
            else { $s=$conn->prepare('INSERT INTO hod_stock(name,qty,price,brand,branch) VALUES(?,?,?,?,?)'); $s->bind_param('sidss',$r['name'],$qty,$r['price'],$r['brand'],$dept); $s->execute(); $toId=$conn->insert_id; $s->close(); }
            $newBoh=(int)$r['qty']-$qty; $s=$conn->prepare('UPDATE principal_stock SET qty=? WHERE id=?'); $s->bind_param('ii',$newBoh,$r['principal_stock_id']); $s->execute(); $s->close();
            $s=$conn->prepare("INSERT INTO inventory_movements(product_id,product_code,item_name,quantity,from_location,to_location,from_stock_id,to_stock_id,department,movement_type,status,requested_by,received_by,received_at) VALUES(?,?,?,?, 'PRINCIPAL_BOH','HOD_FOH',?,?,?,?,'RECEIVED',?,?,NOW())");
            $s->bind_param('issiiisssss',$r['id'],$code,$r['name'],$qty,$r['principal_stock_id'],$toId,$dept,$type,$by,$by); $s->execute(); $s->close();
            $message=$type.' completed: '.$qty.' unit(s) moved to '.$dept.'. Principal balance: '.$newBoh.'.';
        }

        elseif ($action === 'replenishment_request') {
            $code=strtoupper(post('product_code')); $dept=post('department'); $qty=(int)post('quantity'); $by=post('requested_by');
            if($code===''||$dept===''||$qty<=0) fail('Product code, department and positive quantity are required.');
            $s=$conn->prepare('SELECT id,product_code,principal_stock_id FROM inventory_products WHERE product_code=?'); $s->bind_param('s',$code); $s->execute(); $p=$s->get_result()->fetch_assoc(); $s->close(); if(!$p) fail('Unknown product code.');
            $s=$conn->prepare('SELECT name,qty FROM hod_stock WHERE branch=? AND name=(SELECT name FROM principal_stock WHERE id=?) LIMIT 1'); $s->bind_param('si',$dept,$p['principal_stock_id']); $s->execute(); $hs=$s->get_result()->fetch_assoc(); $s->close();
            $current=(int)($hs['qty']??0); $name=(string)($hs['name']??'Product');
            $s=$conn->prepare("INSERT INTO inventory_replenishments(product_id,product_code,item_name,department,current_quantity,requested_quantity,status,requested_by) VALUES(?,?,?,?,?,?,'PENDING',?)"); $s->bind_param('isssiis',$p['id'],$code,$name,$dept,$current,$qty,$by); $s->execute(); $s->close();
            $message='Replenishment request created for '.$name.' in '.$dept.'.';
        }

        elseif ($action === 'outward') {
            $code=strtoupper(post('product_code')); $qty=(int)post('quantity'); $source=post('source_location'); $dept=post('department'); $dest=post('destination'); $reason=post('reason'); $by=post('approved_by');
            if($code===''||$qty<=0||$dest==='') fail('Product code, positive quantity and destination are required.');
            $s=$conn->prepare('SELECT p.id,p.product_code,p.principal_stock_id,s.name,s.qty FROM inventory_products p JOIN principal_stock s ON s.id=p.principal_stock_id WHERE p.product_code=? FOR UPDATE'); $s->bind_param('s',$code); $s->execute(); $p=$s->get_result()->fetch_assoc(); $s->close(); if(!$p) fail('Product not found.');
            if($source==='PRINCIPAL_BOH') { if((int)$p['qty']<$qty) fail('Insufficient Principal Stock.'); $new=(int)$p['qty']-$qty; $s=$conn->prepare('UPDATE principal_stock SET qty=? WHERE id=?'); $s->bind_param('ii',$new,$p['principal_stock_id']); $s->execute(); $s->close(); }
            else { $s=$conn->prepare('SELECT id,qty FROM hod_stock WHERE name=? AND branch=? LIMIT 1 FOR UPDATE'); $s->bind_param('ss',$p['name'],$dept); $s->execute(); $h=$s->get_result()->fetch_assoc(); $s->close(); if(!$h||$h['qty']<$qty) fail('Insufficient HOD stock.'); $new=(int)$h['qty']-$qty; $s=$conn->prepare('UPDATE hod_stock SET qty=? WHERE id=?'); $s->bind_param('ii',$new,$h['id']); $s->execute(); $s->close(); }
            $s=$conn->prepare('INSERT INTO inventory_outward(product_id,product_code,item_name,quantity,source_location,destination,reason,approved_by,handed_over_by) VALUES(?,?,?,?,?,?,?,?,?)'); $s->bind_param('ississsss',$p['id'],$code,$p['name'],$qty,$source,$dest,$reason,$by,$by); $s->execute(); $s->close(); $message='Outward stock completed. '.$qty.' unit(s) permanently removed from '.$source.'.';
        }

        else { fail('Invalid action.'); }
        $conn->commit();
    }
} catch(Throwable $e) { if($conn->errno===0) { /* no-op */ } @$conn->rollback(); $error=$e->getMessage(); }

$counts=[];
foreach ([['Principal','SELECT COALESCE(SUM(qty),0) n FROM principal_stock'],['HOD','SELECT COALESCE(SUM(qty),0) n FROM hod_stock'],['Movements','SELECT COUNT(*) n FROM inventory_movements'],['Replenishment','SELECT COUNT(*) n FROM inventory_replenishments WHERE status="PENDING"']] as $c){$r=$conn->query($c[1])->fetch_assoc();$counts[$c[0]]=$r['n'];}
$search=post('q'); $results=[];
if($search!==''){
 $like='%'.$search.'%';
 $s=$conn->prepare("SELECT p.*, COALESCE(ps.name, hs.name) item_name, COALESCE(ps.qty,hs.qty,0) quantity, COALESCE(ps.brand,hs.brand,'') brand, COALESCE(ps.branch,hs.branch,'') location FROM inventory_products p LEFT JOIN principal_stock ps ON ps.id=p.principal_stock_id LEFT JOIN hod_stock hs ON hs.name=ps.name AND hs.brand=ps.brand WHERE p.product_code LIKE ? OR p.barcode LIKE ? OR p.serial_number LIKE ? OR p.rfid_uid LIKE ? OR p.qr_value LIKE ? LIMIT 25"); $s->bind_param('sssss',$like,$like,$like,$like,$like);$s->execute();$results=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();
}
$movements=$conn->query('SELECT * FROM inventory_movements ORDER BY id DESC LIMIT 30')->fetch_all(MYSQLI_ASSOC);
$reps=$conn->query("SELECT * FROM inventory_replenishments WHERE status='PENDING' ORDER BY id DESC LIMIT 30")->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>College Inventory Control</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f4f7fb;color:#17202a;font:14px Arial,sans-serif}.wrap{max-width:1250px;margin:auto;padding:18px}.hero{background:linear-gradient(135deg,#10233f,#1c5b72);color:white;border-radius:22px;padding:24px;margin-bottom:18px}.hero h1{margin:5px 0;font-size:30px}.hero p{opacity:.85}.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:15px 0}.stat,.card{background:white;border:1px solid #e1e7ef;border-radius:16px;padding:16px;box-shadow:0 4px 18px #10233f0d}.stat b{font-size:25px;display:block}.stat span{color:#697586}.tabs{display:flex;gap:8px;flex-wrap:wrap;margin:15px 0}.tabs a{background:white;border:1px solid #d9e0e8;border-radius:10px;padding:10px 13px;text-decoration:none;color:#203040}.grid{display:grid;grid-template-columns:repeat(2,1fr);gap:15px}.card h2{margin-top:0}.form{display:grid;grid-template-columns:repeat(2,1fr);gap:10px}.form .full{grid-column:1/-1}label{font-size:12px;font-weight:bold;color:#536273}input,select,button{width:100%;padding:11px;border:1px solid #ccd5df;border-radius:9px;background:white}button{background:#173f5f;color:white;font-weight:bold;cursor:pointer;border:0}.ok,.err{padding:12px;border-radius:10px;margin-bottom:12px}.ok{background:#e6f7ee;color:#146b43}.err{background:#ffeded;color:#a12d2d}table{width:100%;border-collapse:collapse;min-width:750px}th,td{padding:9px;border-bottom:1px solid #e7ebf0;text-align:left}th{font-size:11px;color:#667}.table{overflow:auto;margin-top:10px}.badge{padding:4px 7px;border-radius:99px;background:#eaf2f8}@media(max-width:750px){.stats{grid-template-columns:repeat(2,1fr)}.grid,.form{grid-template-columns:1fr}.form .full{grid-column:auto}}
</style></head><body><div class="wrap">
<div class="hero"><small>COLLEGE STOCK INVENTORY MANAGEMENT</small><h1>Inventory Control Center</h1><p>Inward → Principal BOH → Movement → HOD FOH → Replenishment · Outward · RFID · Barcode · QR · Serial Number</p></div>
<?php if($message):?><div class="ok"><?=h($message)?></div><?php endif;?><?php if($error):?><div class="err"><?=h($error)?></div><?php endif;?>
<div class="stats"><?php foreach($counts as $k=>$v):?><div class="stat"><b><?=h($v)?></b><span><?=h($k)?></span></div><?php endforeach;?></div>
<div class="grid">
<div class="card"><h2>Stock IN — Principal BOH</h2><form method="post" class="form"><input type="hidden" name="action" value="inward"><input name="item_name" placeholder="Product name" required><input name="product_code" placeholder="Product code e.g. LAP-001" required><input name="quantity" type="number" min="1" placeholder="Quantity" required><input name="price" type="number" step="0.01" placeholder="Price"><input name="brand" placeholder="Brand"><input name="branch" placeholder="Branch"><input name="barcode" placeholder="Barcode"><input name="serial_number" placeholder="Serial number"><input name="rfid_uid" placeholder="RFID UID"><input name="supplier" placeholder="Supplier"><input name="invoice_no" placeholder="Invoice no."><input name="received_by" placeholder="Received by"><button class="full">Register Stock IN</button></form></div>
<div class="card"><h2>Movement — BOH → HOD FOH</h2><form method="post" class="form"><input type="hidden" name="action" value="movement"><input name="product_code" placeholder="Product code" required><input name="department" placeholder="Department: CE / CT / EE / ME" required><input name="quantity" type="number" min="1" placeholder="Quantity" required><input name="requested_by" placeholder="Requested / received by"><button class="full">Complete Movement</button></form><hr><h2>Replenishment Request</h2><form method="post" class="form"><input type="hidden" name="action" value="replenishment_request"><input name="product_code" placeholder="Product code" required><input name="department" placeholder="Department" required><input name="quantity" type="number" min="1" placeholder="Required quantity" required><input name="requested_by" placeholder="Requested by"><button class="full">Create Replenishment Request</button></form></div>
<div class="card"><h2>Replenishment — Find in Principal</h2><form method="post" class="form"><input type="hidden" name="action" value="replenish"><input name="product_code" placeholder="Product code" required><input name="department" placeholder="Department" required><input name="quantity" type="number" min="1" placeholder="Quantity" required><input name="requested_by" placeholder="Fulfilled by"><button class="full">Find BOH → Move → Replenish HOD</button></form></div>
<div class="card"><h2>Outward — Leave College</h2><form method="post" class="form"><input type="hidden" name="action" value="outward"><input name="product_code" placeholder="Product code" required><input name="quantity" type="number" min="1" placeholder="Quantity" required><select name="source_location"><option>PRINCIPAL_BOH</option><option>HOD_FOH</option></select><input name="department" placeholder="HOD department if FOH"><input name="destination" placeholder="Destination college / organization" required><input name="reason" placeholder="Reason"><input name="approved_by" placeholder="Approved / handed over by"><button class="full">Complete Outward</button></form></div>
</div>
<div class="card" style="margin-top:15px"><h2>Universal Product Finder</h2><form method="get"><div class="form"><input class="full" name="q" value="<?=h($search)?>" placeholder="Product code / barcode / QR value / serial / RFID UID"><button class="full">Search Product</button></div></form><?php if($search!==''):?><div class="table"><table><tr><th>Product Code</th><th>Item</th><th>Barcode</th><th>Serial</th><th>RFID</th><th>Qty</th><th>Location</th></tr><?php foreach($results as $x):?><tr><td><b><?=h($x['product_code'])?></b></td><td><?=h($x['item_name'])?></td><td><?=h($x['barcode'])?></td><td><?=h($x['serial_number'])?></td><td><?=h($x['rfid_uid'])?></td><td><?=h($x['quantity'])?></td><td><?=h($x['location'])?></td></tr><?php endforeach;?><?php if(!$results):?><tr><td colspan="7">No product found.</td></tr><?php endif;?></table></div><?php endif;?></div>
<div class="card" style="margin-top:15px"><h2>Pending Replenishment</h2><div class="table"><table><tr><th>Product</th><th>Department</th><th>Current</th><th>Required</th><th>Requested</th></tr><?php foreach($reps as $x):?><tr><td><?=h($x['product_code'])?> — <?=h($x['item_name'])?></td><td><?=h($x['department'])?></td><td><?=h($x['current_quantity'])?></td><td><?=h($x['requested_quantity'])?></td><td><?=h($x['requested_by'])?></td></tr><?php endforeach;?><?php if(!$reps):?><tr><td colspan="5">No pending replenishment requests.</td></tr><?php endif;?></table></div></div>
<div class="card" style="margin-top:15px"><h2>Movement History</h2><div class="table"><table><tr><th>Date</th><th>Product</th><th>Qty</th><th>From</th><th>To</th><th>Department</th><th>Type</th></tr><?php foreach($movements as $x):?><tr><td><?=h($x['created_at'])?></td><td><?=h($x['product_code'])?> — <?=h($x['item_name'])?></td><td><?=h($x['quantity'])?></td><td><?=h($x['from_location'])?></td><td><?=h($x['to_location'])?></td><td><?=h($x['department'])?></td><td><span class="badge"><?=h($x['movement_type'])?></span></td></tr><?php endforeach;?></table></div></div>
</div></body></html>