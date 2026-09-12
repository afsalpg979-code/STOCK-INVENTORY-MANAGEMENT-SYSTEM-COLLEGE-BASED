<?php
declare(strict_types=1);

/* Standalone upgrade module. Change these only if your MariaDB credentials differ. */
$dbHost = getenv('INVENTORY_DB_HOST') ?: '127.0.0.1';
$dbUser = getenv('INVENTORY_DB_USER') ?: 'root';
$dbPass = getenv('INVENTORY_DB_PASS') ?: '';
$dbName = getenv('INVENTORY_DB_NAME') ?: 'inventory';

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($conn->connect_errno) {
    http_response_code(500);
    exit('Database connection failed: ' . htmlspecialchars($conn->connect_error));
}
$conn->set_charset('utf8mb4');

function h(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function post(string $key, string $default = ''): string { return trim((string)($_POST[$key] ?? $default)); }
function fail(string $message): never { throw new RuntimeException($message); }
