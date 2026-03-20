<?php
// ================================================
// video_delete_section.php
// ================================================

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'ID inválido.'];
    header("Location: ?action=videos");
    exit;
}

// Chequear permiso
$sucursales_usuario = getSucursalesAcceso($pdo, $_SESSION['usuario_id'], $_SESSION['rol']);
$stmt = $pdo->prepare("SELECT sucursal_id FROM video_sucursal WHERE video_id = ?");
$stmt->execute([$id]);
$sucursales_video = $stmt->fetchAll(PDO::FETCH_COLUMN);

$tiene_acceso = $_SESSION['rol'] === 'admin' || 
                array_intersect($sucursales_usuario, $sucursales_video);

if (!$tiene_acceso) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'No tienes permiso para eliminar este video.'];
    header("Location: ?action=videos");
    exit;
}

// Eliminar
try {
    $stmt = $pdo->prepare("SELECT filename, thumbnail FROM videos WHERE id = ?");
    $stmt->execute([$id]);
    $video = $stmt->fetch();

    if ($video) {
        $video_path = VIDEO_DIR . $video['filename'];
        $thumb_path = THUMB_DIR . $video['thumbnail'];

        if (file_exists($video_path)) unlink($video_path);
        if (file_exists($thumb_path)) unlink($thumb_path);

        $pdo->prepare("DELETE FROM videos WHERE id = ?")->execute([$id]);

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Video eliminado correctamente'];
    }
} catch (PDOException $e) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Error al eliminar: ' . $e->getMessage()];
}

header("Location: ?action=videos");
exit;