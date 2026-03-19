<?php
// api/heartbeat.php
header('Content-Type: application/json; charset=utf-8');
require_once '../config.php';

$client_key = trim($_GET['key'] ?? $_POST['key'] ?? '');

if (strlen($client_key) !== 32 || !ctype_alnum($client_key)) {
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'message' => 'client_key inválido (debe ser exactamente 32 caracteres alfanuméricos)'
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("
    SELECT id, playlist_version, orientation, background, last_ping
    FROM clients
    WHERE client_key = ?
    ");
    $stmt->execute([$client_key]);
    $client = $stmt->fetch();

    if (!$client) {
        // Auto-registro si no existe (opcional - podés quitarlo si preferís solo manual)
        $stmt = $pdo->prepare("
        INSERT INTO clients
        (name, client_key, orientation, background, active, playlist_version, last_ping)
        VALUES (?, ?, 'horizontal', '', 1, 0, NOW())
        ");
        $stmt->execute(["TV-" . substr(md5($client_key), 0, 8), $client_key]);

        $client_id = $pdo->lastInsertId();
        $playlist_version = 0;
        $orientation = 'horizontal';
        $background = '';
    } else {
        $client_id = $client['id'];
        $playlist_version = $client['playlist_version'];
        $orientation = $client['orientation'];
        $background = $client['background'];
    }

    // Siempre actualizar last_ping y active
    $pdo->prepare("
    UPDATE clients
    SET last_ping = NOW(), active = 1
    WHERE id = ?
    ")->execute([$client_id]);

    echo json_encode([
        'status'             => 'ok',
        'client_id'          => $client_id,
        'playlist_version'   => (int)$playlist_version,
                     'orientation'        => $orientation,
                     'background'         => $background ?: null,
                     'must_update'        => false,           // el cliente compara con su versión local
                     'server_time'        => date('c'),       // útil para sincronización futura
                     'poll_interval_sec'  => 30               // sugerencia de polling
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Error interno del servidor'
    ]);
}
exit;
