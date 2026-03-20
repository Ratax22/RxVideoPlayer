<?php
session_start();
require_once '../config.php';
require_once 'proteccion.php';

// ID del video
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'ID inválido.'];
    header("Location: video.php");
    exit;
}

// Verificar permiso
$sucursales_usuario = getSucursalesAcceso($pdo, $_SESSION['usuario_id'], $_SESSION['rol']);
$stmt = $pdo->prepare("SELECT sucursal_id FROM video_sucursal WHERE video_id = ?");
$stmt->execute([$id]);
$sucursales_video = $stmt->fetchAll(PDO::FETCH_COLUMN);

$tiene_acceso = $_SESSION['rol'] === 'admin' || 
                array_intersect($sucursales_usuario, $sucursales_video);

if (!$tiene_acceso) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'No tienes permiso para eliminar este video.'];
    header("Location: video.php");
    exit;
}

// Eliminar
try {
    $stmt = $pdo->prepare("SELECT filename, thumbnail FROM videos WHERE id = ?");
    $stmt->execute([$id]);
    $video = $stmt->fetch();

    if ($video) {
        // Borrar archivos físicos
        $video_path = VIDEO_DIR . $video['filename'];
        $thumb_path = THUMB_DIR . $video['thumbnail'];
        if (file_exists($video_path)) unlink($video_path);
        if (file_exists($thumb_path)) unlink($thumb_path);

        // Borrar de BD (cascada borra video_sucursal)
        $pdo->prepare("DELETE FROM videos WHERE id = ?")->execute([$id]);

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Video eliminado correctamente'];
    }
} catch (PDOException $e) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Error al eliminar: ' . $e->getMessage()];
}

header("Location: video.php");
exit;