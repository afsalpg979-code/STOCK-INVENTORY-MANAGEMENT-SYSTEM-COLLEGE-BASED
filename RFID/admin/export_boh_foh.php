<?php

declare(strict_types=1);

require_once __DIR__ . '/../api/config.php';

$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$format = strtolower(trim((string)($_GET['format'] ?? 'xlsx')));

$sql = "SELECT moved_at, uid, item_name, item_code, quantity,
               from_zone, to_zone, from_department, to_department,
               moved_by, device_uid, notes
        FROM rfid_boh_foh_movements";
$where = [];
$types = '';
$params = [];

if ($from !== '' && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $from)) {
    $where[] = 'moved_at >= ?';
    $types .= 's';
    $params[] = $from . ' 00:00:00';
}
if ($to !== '' && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $to)) {
    $where[] = 'moved_at <= ?';
    $types .= 's';
    $params[] = $to . ' 23:59:59';
}
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY moved_at DESC, id DESC';

$stmt = $conn->prepare($sql);
if ($types !== '') $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$filename = 'boh-foh-movement-report-' . date('Y-m-d-His');

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Moved At','RFID UID','Item','Item Code','Quantity','From','To','From Department','To Department','Moved By','Device','Notes']);
    while ($row = $result->fetch_assoc()) {
        fputcsv($out, array_values($row));
    }
    fclose($out);
    exit;
}

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');
?>
<!doctype html>
<html><head><meta charset="utf-8"><style>
body{font-family:Arial,sans-serif}h2{margin-bottom:4px}p{color:#555}table{border-collapse:collapse;width:100%}th,td{border:1px solid #999;padding:7px;text-align:left}th{background:#263238;color:#fff}.num{text-align:right}
</style></head><body>
<h2>BOH → FOH Stock Movement Report</h2>
<p>Generated: <?= htmlspecialchars(date('d-m-Y H:i:s')) ?> | From: <?= htmlspecialchars($from ?: 'All') ?> | To: <?= htmlspecialchars($to ?: 'All') ?></p>
<table><thead><tr>
<th>Moved At</th><th>RFID UID</th><th>Item</th><th>Item Code</th><th>Quantity</th><th>From</th><th>To</th><th>From Department</th><th>To Department</th><th>Moved By</th><th>Device</th><th>Notes</th>
</tr></thead><tbody>
<?php while ($row = $result->fetch_assoc()): ?>
<tr><?php foreach ($row as $key => $value): ?><td class="<?= $key === 'quantity' ? 'num' : '' ?>"><?= htmlspecialchars((string)($value ?? '')) ?></td><?php endforeach; ?></tr>
<?php endwhile; ?>
</tbody></table></body></html>
<?php
$stmt->close();
