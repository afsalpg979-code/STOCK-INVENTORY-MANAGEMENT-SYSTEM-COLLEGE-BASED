<?php

declare(strict_types=1);

require_once __DIR__ . '/../api/config.php';

$movements = [];
$result = $conn->query(
    "SELECT id, uid, item_name, item_code, quantity, from_zone, to_zone,
            from_department, to_department, moved_by, moved_at
     FROM rfid_boh_foh_movements
     ORDER BY id DESC
     LIMIT 100"
);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $movements[] = $row;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>RFID BOH → FOH Movement</title>
<style>
:root{--bg:#071018;--panel:#101c26;--line:#263746;--text:#eef7fb;--muted:#9db0ba;--accent:#20c997;--danger:#ff6b6b}
*{box-sizing:border-box}body{margin:0;background:linear-gradient(135deg,#071018,#102331);color:var(--text);font-family:Inter,Segoe UI,Arial,sans-serif;padding:22px}.wrap{max-width:1180px;margin:auto}.hero{background:rgba(16,28,38,.94);border:1px solid var(--line);border-radius:22px;padding:24px;margin-bottom:18px}.badge{display:inline-block;border:1px solid #31505d;border-radius:999px;padding:6px 10px;color:#9fe8d0;font-size:12px}.hero h1{margin:12px 0 7px;font-size:clamp(25px,4vw,38px)}.hero p{color:var(--muted);line-height:1.6}.flow{display:grid;grid-template-columns:1fr auto 1fr;gap:12px;align-items:center;margin-top:18px}.zone{padding:16px;border:1px solid var(--line);border-radius:15px;background:#0b151d}.zone strong{display:block;font-size:19px}.zone small{color:var(--muted)}.arrow{font-size:25px;color:var(--accent)}.panel{background:rgba(16,28,38,.94);border:1px solid var(--line);border-radius:22px;padding:20px;overflow:auto}.grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}.stat{padding:15px;border-radius:14px;background:#0b151d;border:1px solid var(--line)}.stat b{font-size:22px}.stat span{display:block;color:var(--muted);font-size:12px;margin-top:4px}table{width:100%;border-collapse:collapse;margin-top:18px;min-width:850px}th,td{text-align:left;padding:11px;border-bottom:1px solid var(--line);font-size:13px}th{color:#a9c1cb;font-size:12px;text-transform:uppercase}code{color:#9fe8d0}@media(max-width:650px){body{padding:12px}.flow{grid-template-columns:1fr}.arrow{text-align:center;transform:rotate(90deg)}.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">
<section class="hero">
<span class="badge">RFID INVENTORY CONTROL</span>
<h1>BOH → FOH Stock Movement</h1>
<p><b>BOH</b> represents Principal/Central stock. <b>FOH</b> represents the department/other operational stock. When stock is moved, BOH must decrease and the FOH destination must receive the moved quantity, with an exact movement timestamp and audit record.</p>
<div class="flow"><div class="zone"><strong>BOH</strong><small>Principal / Central Stock</small></div><div class="arrow">→</div><div class="zone"><strong>FOH</strong><small>Department / Other Stock</small></div></div>
</section>
<section class="panel">
<div class="grid">
<div class="stat"><b><?= count($movements) ?></b><span>Recent movements shown</span></div>
<div class="stat"><b>BOH → FOH</b><span>Controlled movement direction</span></div>
</div>
<table>
<thead><tr><th>Time</th><th>RFID</th><th>Item</th><th>Qty</th><th>From</th><th>To</th><th>Department</th><th>Moved By</th></tr></thead>
<tbody>
<?php foreach ($movements as $m): ?>
<tr>
<td><?= htmlspecialchars((string)$m['moved_at']) ?></td>
<td><code><?= htmlspecialchars((string)$m['uid']) ?></code></td>
<td><?= htmlspecialchars((string)$m['item_name']) ?><br><small><?= htmlspecialchars((string)($m['item_code'] ?? '')) ?></small></td>
<td><b><?= (int)$m['quantity'] ?></b></td>
<td><?= htmlspecialchars((string)$m['from_zone']) ?></td>
<td><?= htmlspecialchars((string)$m['to_zone']) ?></td>
<td><?= htmlspecialchars((string)($m['to_department'] ?? '')) ?></td>
<td><?= htmlspecialchars((string)($m['moved_by'] ?? '')) ?></td>
</tr>
<?php endforeach; ?>
<?php if (!$movements): ?><tr><td colspan="8">No BOH → FOH movements recorded yet.</td></tr><?php endif; ?>
</tbody>
</table>
</section>
</div>
</body>
</html>
