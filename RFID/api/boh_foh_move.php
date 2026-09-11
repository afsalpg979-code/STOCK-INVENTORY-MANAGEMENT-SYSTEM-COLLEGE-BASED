<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'POST required'], 405);
}

$apiKey = $_SERVER['HTTP_X_RFID_API_KEY'] ?? '';
if (!hash_equals(RFID_API_KEY, $apiKey)) {
    json_response(['success' => false, 'message' => 'Unauthorized'], 401);
}

$data = get_json_input();

$uid = strtoupper(trim((string)($data['uid'] ?? '')));
$quantity = (int)($data['quantity'] ?? 0);
$toDepartment = trim((string)($data['to_department'] ?? ''));
$movedBy = trim((string)($data['moved_by'] ?? ''));
$deviceUid = trim((string)($data['device_uid'] ?? ''));
$notes = trim((string)($data['notes'] ?? ''));

if ($uid === '' || !preg_match('/^[0-9A-F]{4,32}$/', $uid)) {
    json_response(['success' => false, 'message' => 'Valid RFID UID required'], 400);
}

if ($quantity <= 0) {
    json_response(['success' => false, 'message' => 'Quantity must be greater than zero'], 400);
}

if ($toDepartment === '') {
    json_response(['success' => false, 'message' => 'Destination department required'], 400);
}

try {
    $conn->begin_transaction();

    // Lock the RFID item while the BOH -> FOH transaction is performed.
    $stmt = $conn->prepare(
        "SELECT id, uid, item_name, item_code, department, quantity, stock_zone, status
         FROM rfid_tags
         WHERE uid = ?
         LIMIT 1
         FOR UPDATE"
    );
    $stmt->bind_param('s', $uid);
    $stmt->execute();
    $result = $stmt->get_result();
    $tag = $result->fetch_assoc();
    $stmt->close();

    if (!$tag) {
        $conn->rollback();
        json_response(['success' => false, 'status' => 'UNKNOWN_TAG', 'message' => 'RFID tag is not registered'], 404);
    }

    if ($tag['status'] !== 'active') {
        $conn->rollback();
        json_response(['success' => false, 'status' => 'BLOCKED', 'message' => 'RFID tag is not active'], 403);
    }

    if (($tag['stock_zone'] ?? 'BOH') !== 'BOH') {
        $conn->rollback();
        json_response(['success' => false, 'status' => 'NOT_IN_BOH', 'message' => 'This RFID item is not currently in BOH'], 409);
    }

    $bohQuantity = (int)$tag['quantity'];
    if ($quantity > $bohQuantity) {
        $conn->rollback();
        json_response([
            'success' => false,
            'status' => 'INSUFFICIENT_BOH',
            'message' => 'Not enough BOH stock',
            'boh_quantity' => $bohQuantity,
            'requested_quantity' => $quantity
        ], 409);
    }

    $remainingBoh = $bohQuantity - $quantity;

    // Keep the original RFID record as BOH when some stock remains.
    $update = $conn->prepare(
        "UPDATE rfid_tags
         SET quantity = ?
         WHERE id = ?"
    );
    $tagId = (int)$tag['id'];
    $update->bind_param('ii', $remainingBoh, $tagId);
    $update->execute();
    $update->close();

    // Create a FOH movement record. The existing project department stock tables
    // can consume this transaction without changing their unknown legacy schema.
    $move = $conn->prepare(
        "INSERT INTO rfid_boh_foh_movements
         (rfid_tag_id, uid, item_name, item_code, quantity, from_zone, to_zone,
          from_department, to_department, moved_by, device_uid, notes)
         VALUES (?, ?, ?, ?, ?, 'BOH', 'FOH', ?, ?, ?, ?, ?)"
    );

    $fromDepartment = (string)($tag['department'] ?? 'Principal');
    $itemName = (string)$tag['item_name'];
    $itemCode = $tag['item_code'] !== null ? (string)$tag['item_code'] : null;
    $move->bind_param(
        'isssisssss',
        $tagId,
        $uid,
        $itemName,
        $itemCode,
        $quantity,
        $fromDepartment,
        $toDepartment,
        $movedBy,
        $deviceUid,
        $notes
    );
    $move->execute();
    $move->close();

    $log = $conn->prepare(
        "INSERT INTO rfid_scan_logs
         (uid, rfid_tag_id, device_uid, operation, result, username, department, ip_address)
         VALUES (?, ?, ?, 'STOCK_OUT', 'SUCCESS', ?, ?, ?)"
    );
    $username = $movedBy !== '' ? $movedBy : null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $log->bind_param('sissss', $uid, $tagId, $deviceUid, $username, $toDepartment, $ip);
    $log->execute();
    $log->close();

    $conn->commit();

    json_response([
        'success' => true,
        'status' => 'BOH_TO_FOH_SUCCESS',
        'uid' => $uid,
        'item' => $itemName,
        'quantity_moved' => $quantity,
        'boh_remaining' => $remainingBoh,
        'from' => 'BOH',
        'to' => 'FOH',
        'to_department' => $toDepartment,
        'moved_at' => date('Y-m-d H:i:s')
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    error_log('RFID BOH->FOH error: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'BOH to FOH movement failed'], 500);
}
