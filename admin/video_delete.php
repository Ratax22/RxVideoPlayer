<?php

session_start();
require_once '../config.php';       // conexión + constantes
require_once 'proteccion.php';      // chequeo de sesión y rol

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) die("ID inválido.");

$stmt = $pdo->prepare("SELECT filename, thumbnail FROM videos WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch();

if ($row) {
    @unlink(VIDEO_DIR . $row['filename']);
    @unlink(THUMB_DIR . $row['thumbnail']);

    $pdo->prepare("DELETE FROM videos WHERE id = ?")->execute([$id]);
    // También borra asignaciones en video_client si existen
    $pdo->prepare("DELETE FROM video_client WHERE video_id = ?")->execute([$id]);
}

header("Location: videos.php?msg=Eliminado");
exit;
