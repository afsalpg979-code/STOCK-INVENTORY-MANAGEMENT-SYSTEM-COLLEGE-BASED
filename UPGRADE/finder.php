<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

$q = trim($_GET['q'] ?? '');
$zone = strtoupper(trim($_GET['zone'] ?? 'ALL'));
$department = trim($_GET['department'] ?? 'ALL');
$stock = $_GET['stock'] ?? 'ALL';
if (!in_array($zone, ['ALL','BOH','FOH'], true)) $zone = 'ALL';
if (!in_array($stock, ['ALL','LOW','EMPTY'], true)) $stock = 'ALL';

$rows = [];
$departments = [];
$r = $conn->query("SELECT DISTINCT branch FROM hod_stock WHERE branch <> '' ORDER BY branch");
while ($x = $r->fetch_assoc()) $departments[] = $x['branch'];

$conditions = [];
$params = [];
types = '';
if ($q !== '') {
    $like = '%' . $q . '%';
    $conditions[] = '(p.product_code LIKE ? OR p.barcode LIKE ? OR p.serial_number LIKE ? OR p.qr_value LIKE ? OR p.rfid_uid LIKE ? OR ps.name LIKE ? OR ps.brand LIKE ?)';
    $params = array_merge($params, [$like,$like,$like,$like,$like,$like,$like]);
    $types .= 'sssssss';
}
if ($department !== 'ALL') { $conditions[] = 'h.branch = ?'; $params[] = $department; $types .= 's'; }
if ($stock === 'LOW') { $conditions[] = 'h.qty BETWEEN 1 AND 5'; }
if ($stock === 'EMPTY') { $conditions[] = 'h.qty <= 0'; }
$where = $conditions ? ' AND ' . implode(' AND ', $conditions) : '';

if ($zone !== 'FOH') {
    $c = $q !== '' ? '(p.product_code LIKE ? OR p.barcode LIKE ? OR p.serial_number LIKE ? OR p.qr_value LIKE ? OR p.rfid_uid LIKE ? OR ps.name LIKE ? OR ps.brand LIKE ?)' : '1=1';
    $paramsB = $q !== '' ? [$like,$like,$like,$like,$like,$like,$like] : [];
    $typesB = $q !== '' ? 'sssssss' : '';
    $sql = "SELECT p.id,p.product_code,p.barcode,p.serial_number,p.qr_value,p.rfid_uid,ps.name item_name,ps.brand,ps.qty quantity,'BOH' zone,'Principal / BOH' location,ps.branch department
            FROM inventory_products p JOIN principal_stock ps ON ps.id=p.principal_stock_id WHERE $c";
    if ($stock === 'LOW') $sql .= ' AND ps.qty BETWEEN 1 AND 5';
    if ($stock === 'EMPTY') $sql .= ' AND ps.qty <= 0';
    $s=$conn->prepare($sql); if($typesB!=='') $s->bind_param($typesB,...$paramsB); $s->execute(); $rows=array_merge($rows,$s->get_result()->fetch_all(MYSQLI_ASSOC)); $s->close();
}

if ($zone !== 'BOH') {
    $c = $q !== '' ? '(p.product_code LIKE ? OR p.barcode LIKE ? OR p.serial_number LIKE ? OR p.qr_value LIKE ? OR p.rfid_uid LIKE ? OR ps.name LIKE ? OR ps.brand LIKE ?)' : '1=1';
    $extra = $department !== 'ALL' ? ' AND h.branch = ?' : '';
    if ($stock === 'LOW') $extra .= ' AND h.qty BETWEEN 1 AND 5';
    if ($stock === 'EMPTY') $extra .= ' AND h.qty <= 0';
    $sql = "SELECT p.id,p.product_code,p.barcode,p.serial_number,p.qr_value,p.rfid_uid,ps.name item_name,ps.brand,h.qty quantity,'FOH' zone,CONCAT('HOD / FOH - ',h.branch) location,h.branch department
            FROM inventory_products p JOIN principal_stock ps ON ps.id=p.principal_stock_id JOIN hod_stock h ON h.name=ps.name AND h.brand=ps.brand WHERE $c$extra";
    $pp = $q !== '' ? [$like,$like,$like,$like,$like,$like,$like] : [];
    $tt = $q !== '' ? 'sssssss' : '';
    if ($department !== 'ALL') { $pp[]=$department; $tt.='s'; }
    $s=$conn->prepare($sql); if($tt!=='') $s->bind_param($tt,...$pp); $s->execute(); $rows=array_merge($rows,$s->get_result()->fetch_all(MYSQLI_ASSOC)); $s->close();
}

usort($rows, fn($a,$b)=>[$a['item_name'],$a['zone'],$a['department']] <=> [$b['item_name'],$b['zone'],$b['department']]);
function esc($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Universal Product Finder</title>
<style>
:root{--nav:#0b1930;--accent:#1666c5;--bg:#f4f7fb;--line:#e3e8ef;--text:#17202a;--muted:#687586}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font-family:Inter,system-ui,Arial,sans-serif}.shell{max-width:1400px;margin:auto;padding:20px}.top{background:linear-gradient(135deg,#0b1930,#155d78);color:#fff;border-radius:24px;padding:24px;box-shadow:0 12px 35px #0b193018}.top h1{margin:5px 0 7px;font-size:30px}.top p{margin:0;opacity:.82}.filters,.panel{background:#fff;border:1px solid var(--line);border-radius:18px;margin-top:16px;padding:18px;box-shadow:0 5px 20px #17202a0b}.filters{display:grid;grid-template-columns:2.2fr 1fr 1fr 1fr auto;gap:10px;align-items:end}.field label{display:block;font-size:12px;font-weight:700;color:var(--muted);margin-bottom:6px}.field input,.field select,button,.btn{width:100%;padding:12px;border:1px solid #ccd5df;border-radius:10px;background:#fff;color:var(--text);text-decoration:none}.primary{background:var(--accent);color:#fff;border-color:var(--accent);font-weight:700}.summary{display:flex;gap:10px;flex-wrap:wrap;margin:16px 0}.pill{background:#eef4fa;border-radius:999px;padding:8px 12px;font-size:13px}.tablewrap{overflow:auto}table{width:100%;border-collapse:collapse;min-width:1100px}th,td{padding:11px 10px;border-bottom:1px solid var(--line);text-align:left}th{font-size:11px;text-transform:uppercase;color:var(--muted);background:#fafbfd;position:sticky;top:0}.zone{font-weight:800;border-radius:999px;padding:5px 9px;display:inline-block;background:#eef4fa}.code{font-family:ui-monospace,SFMono-Regular,monospace;font-weight:700}.muted{color:var(--muted);font-size:12px}.empty{padding:35px;text-align:center;color:var(--muted)}.back{display:inline-block;margin-top:12px;color:#fff;text-decoration:none;font-weight:600}@media(max-width:900px){.filters{grid-template-columns:1fr 1fr}.filters .wide{grid-column:1/-1}}@media(max-width:560px){.filters{grid-template-columns:1fr}}
</style></head><body><main class="shell">
<section class="top"><small>COLLEGE INVENTORY • UNIVERSAL SEARCH</small><h1>Product Finder</h1><p>Search one product across Principal BOH and HOD FOH using Product ID, Code, Barcode, QR, Serial Number or RFID UID.</p><a class="back" href="index.php">← Back to Inventory Control Center</a></section>
<form class="filters" method="get">
<div class="field wide"><label>Universal Search</label><input name="q" value="<?=esc($q)?>" placeholder="Product code / barcode / QR / serial / RFID / name / brand"></div>
<div class="field"><label>Stock Location</label><select name="zone"><option value="ALL" <?=$zone==='ALL'?'selected':''?>>All BOH + FOH</option><option value="BOH" <?=$zone==='BOH'?'selected':''?>>BOH — Principal</option><option value="FOH" <?=$zone==='FOH'?'selected':''?>>FOH — HOD</option></select></div>
<div class="field"><label>Department</label><select name="department"><option value="ALL">All Departments</option><?php foreach($departments as $d):?><option value="<?=esc($d)?>" <?=$department===$d?'selected':''?>><?=esc($d)?></option><?php endforeach;?></select></div>
<div class="field"><label>Stock Status</label><select name="stock"><option value="ALL">All Stock</option><option value="LOW" <?=$stock==='LOW'?'selected':''?>>Low (1–5)</option><option value="EMPTY" <?=$stock==='EMPTY'?'selected':''?>>Empty (0)</option></select></div>
<button class="primary" type="submit">Search</button>
</form>
<div class="summary"><span class="pill"><b><?=count($rows)?></b> matching stock records</span><a class="btn" style="width:auto" href="export.php?<?=http_build_query($_GET)?>">Export Excel</a><a class="btn" style="width:auto" href="export.php?format=csv&<?=http_build_query($_GET)?>">Export CSV</a></div>
<section class="panel"><div class="tablewrap"><table><thead><tr><th>Product</th><th>Code</th><th>Barcode</th><th>Serial</th><th>QR</th><th>RFID UID</th><th>Location</th><th>Department</th><th>Qty</th></tr></thead><tbody>
<?php if(!$rows):?><tr><td colspan="9" class="empty">No matching product found. Try another identifier or select “All BOH + FOH”.</td></tr><?php else: foreach($rows as $r):?><tr><td><b><?=esc($r['item_name'])?></b><div class="muted"><?=esc($r['brand'])?></div></td><td class="code"><?=esc($r['product_code'])?></td><td><?=esc($r['barcode'] ?: '—')?></td><td><?=esc($r['serial_number'] ?: '—')?></td><td><?=esc($r['qr_value'] ?: '—')?></td><td><?=esc($r['rfid_uid'] ?: '—')?></td><td><span class="zone"><?=esc($r['zone'])?></span><div class="muted"><?=esc($r['location'])?></div></td><td><?=esc($r['department'] ?: '—')?></td><td><b><?=esc($r['quantity'])?></b></td></tr><?php endforeach; endif;?></tbody></table></div></section>
</main></body></html>
