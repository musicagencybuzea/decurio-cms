<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
require_login();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method']);
    exit;
}
if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'csrf']);
    exit;
}
global $config;
require dirname(__DIR__) . '/app/linkfinder.php';
$result = find_links(trim((string)($_POST['spotify_url'] ?? '')), $config);
if (isset($result['error']) && $result['error'] === 'invalid_url') {
    echo json_encode(['error' => 'invalid_url']);
    exit;
}
echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
