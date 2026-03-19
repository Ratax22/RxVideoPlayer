<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

require_once '../config.php';

$client_key = trim($_GET['key'] ?? $_POST['key'] ?? '');
$client_version = (int)($_GET['v'] ?? $_POST['v'] ?? 0);

if (strlen($client_key) !== 32 || !ctype_alnum($client_key)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'client_key inválido']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, playlist_version, orientation, background FROM clients WHERE client_key = ?");
    $stmt->execute([$client_key]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$client) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Cliente no encontrado']);
        exit;
    }

    $pdo->prepare("UPDATE clients SET last_ping = NOW(), active = 1 WHERE id = ?")->execute([$client['id']]);

    $base_url = 'https://' . $_SERVER['HTTP_HOST'] . '/';  // ajusta si es necesario

    if ($client_version >= $client['playlist_version']) {
        echo json_encode([
            'status'           => 'ok',
            'update_required'  => false,
            'current_version'  => (int)$client['playlist_version'],
                         'message'          => 'Playlist actual (sin cambios)',
                         'orientation'      => $client['orientation'],
                         'background'       => $client['background'] ? $base_url . $client['background'] : null
        ]);
        exit;
    }

    $stmt = $pdo->prepare("
    SELECT v.id, v.title, v.filename, v.thumbnail, vc.play_order
    FROM video_client vc
    INNER JOIN videos v ON vc.video_id = v.id
    WHERE vc.client_id = ?
    ORDER BY vc.play_order ASC
    ");
    $stmt->execute([$client['id']]);
    $playlist_raw = $stmt->fetchAll();

    $videos = [];
    foreach ($playlist_raw as $item) {
        $videos[] = [
            'id'        => (int)$item['id'],
            'title'     => $item['title'],
            'url'       => $base_url . 'videos/' . $item['filename'],
            'thumbnail' => $base_url . 'images/thumbs/' . $item['thumbnail'],
            'order'     => (int)$item['play_order']
        ];
    }

    echo json_encode([
        'status'           => 'ok',
        'update_required'  => true,
        'current_version'  => (int)$client['playlist_version'],
                     'orientation'      => $client['orientation'],
                     'background'       => $client['background'] ? $base_url . $client['background'] : null,
                     'playlist'         => $videos,
                     'count'            => count($videos),
                     'timestamp'        => date('c')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error interno']);
}
exit;
