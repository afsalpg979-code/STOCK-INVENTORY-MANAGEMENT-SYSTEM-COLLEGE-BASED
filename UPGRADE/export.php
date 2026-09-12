<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

$q=trim($_GET['q']??''); $zone=strtoupper(trim($_GET['zone']??'ALL')); $dept=trim($_GET['department']??'ALL'); $stock=$_GET['stock']??'ALL';
$like='%'.$q.'%';
$rows=[];
$base="SELECT p.id,p.product_code,p.barcode,p.serial_number,p.qr_value,p.rfid_uid,ps.name item_name,ps.brand,ps.qty quantity,'BOH' zone,'Principal / BOH' location,ps.branch department FROM inventory_products p JOIN principal_stock ps ON ps.id=p.principal_stock_id WHERE 1=1";
$conds=$q!==''?" AND (p.product_code LIKE ? OR p.barcode LIKE ? OR p.serial_number LIKE ? OR p.qr_value LIKE ? OR p.rfid_uid LIKE ? OR ps.name LIKE ? OR ps.brand LIKE ?)":'';
if($zone!=='FOH'){
 $sql=$base.$conds; if($stock==='LOW')$sql.=' AND ps.qty BETWEEN 1 AND 5'; if($stock==='EMPTY')$sql.=' AND ps.qty<=0';
 $s=$conn->prepare($sql); if($q!=='')$s->bind_param('sssssss',$like,$like,$like,$like,$like,$like,$like); $s->execute(); $rows=array_merge($rows,$s->get_result()->fetch_all(MYSQLI_ASSOC)); $s->close();
}
if($zone!=='BOH'){
 $sql="SELECT p.id,p.product_code,p.barcode,p.serial_number,p.qr_value,p.rfid_uid,ps.name item_name,ps.brand,h.qty quantity,'FOH' zone,CONCAT('HOD / FOH - ',h.branch) location,h.branch department FROM inventory_products p JOIN principal_stock ps ON ps.id=p.principal_stock_id JOIN hod_stock h ON h.name=ps.name AND h.brand=ps.brand WHERE 1=1".$conds;
 $types='';$params=[];if($q!==''){ $types='sssssss';$params=[$like,$like,$like,$like,$like,$like,$like]; }
 if($dept!=='ALL'){ $sql.=' AND h.branch=?';$types.='s';$params[]=$dept; } if($stock==='LOW')$sql.=' AND h.qty BETWEEN 1 AND 5'; if($stock==='EMPTY')$sql.=' AND h.qty<=0';
 $s=$conn->prepare($sql);if($types!=='')$s->bind_param($types,...$params);$s->execute();$rows=array_merge($rows,$s->get_result()->fetch_all(MYSQLI_ASSOC));$s->close();
}
$format=$_GET['format']??'xls';
if($format==='csv'){
 header('Content-Type:text/csv; charset=utf-8'); header('Content-Disposition:attachment; filename="college_inventory_export_'.date('Y-m-d').'.csv"');
 $out=fopen('php://output','w'); fputcsv($out,['Product ID','Product Name','Product Code','Barcode','Serial Number','QR Value','RFID UID','Location','Department','Quantity','Brand']);
 foreach($rows as $r)fputcsv($out,[$r['id'],$r['item_name'],$r['product_code'],$r['barcode'],$r['serial_number'],$r['qr_value'],$r['rfid_uid'],$r['location'],$r['department'],$r['quantity'],$r['brand']]); fclose($out); exit;
}
header('Content-Type:application/vnd.ms-excel; charset=utf-8');header('Content-Disposition:attachment; filename="college_inventory_export_'.date('Y-m-d').'.xls"');
function e($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
?><html><head><meta charset="utf-8"></head><body><h2>College Inventory — Product Export</h2><table border="1"><tr><th>Product ID</th><th>Product Name</th><th>Product Code</th><th>Barcode</th><th>Serial Number</th><th>QR Value</th><th>RFID UID</th><th>Location</th><th>Department</th><th>Quantity</th><th>Brand</th></tr><?php foreach($rows as $r):?><tr><td><?=e($r['id'])?></td><td><?=e($r['item_name'])?></td><td><?=e($r['product_code'])?></td><td><?=e($r['barcode'])?></td><td><?=e($r['serial_number'])?></td><td><?=e($r['qr_value'])?></td><td><?=e($r['rfid_uid'])?></td><td><?=e($r['location'])?></td><td><?=e($r['department'])?></td><td><?=e($r['quantity'])?></td><td><?=e($r['brand'])?></td></tr><?php endforeach;?></table></body></html>
