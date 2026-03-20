<?php
require_once '../config.php';

$video_id = (int)($_GET['video_id'] ?? 0);
$client_key = trim($_GET['client_key'] ?? '');

if ($video_id > 0 && strlen($client_key) === 32) {
    $stmt = $pdo->prepare("UPDATE videos SET reproducciones = reproducciones + 1 WHERE id = ?");
    $stmt->execute([$video_id]);
    echo json_encode(['status' => 'ok']);
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error']);
}
?>