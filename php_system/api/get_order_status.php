<?php
require_once '../config.php';

header('Content-Type: application/json');

$token = isset($_GET['token']) ? $_GET['token'] : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($token !== API_SECRET_TOKEN) {
    http_response_code(403);
    die(json_encode(['error' => 'Unauthorized']));
}

if (!$id) {
    die(json_encode(['error' => 'Missing ID']));
}

try {
    $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
    $stmt->execute([$id]);
    $status = $stmt->fetchColumn();

    echo json_encode(['success' => true, 'status' => $status]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error']);
}
