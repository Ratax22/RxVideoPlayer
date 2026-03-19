<?php
session_start();
// ================================================
// admin/video_upload.php - Procesa subida y optimización
// ================================================
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['video']) || $_FILES['video']['error'] !== 0) {
    die("Error en la subida del archivo.");
}

$title = trim($_POST['title'] ?? '');
if (empty($title)) $title = 'Video sin título';

$tmpFile   = $_FILES['video']['tmp_name'];
$origName  = basename($_FILES['video']['name']);
$extension = '.mp4'; // siempre MP4
$filename  = time() . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '', pathinfo($origName, PATHINFO_FILENAME)) . $extension;

$finalPath = VIDEO_DIR . $filename;
$thumbName = time() . '_thumb.jpg';
$thumbPath = THUMB_DIR . $thumbName;

// ================= SECCIÓN: Optimización con FFmpeg =================
// Settings recomendados 2024-2025: H.264, CRF 23 (buen balance calidad/tamaño), faststart para web
$ffmpegCmd = escapeshellcmd(FFMPEG_PATH) . " -i " . escapeshellarg($tmpFile) .
    " -c:v libx264 -preset medium -crf 23 -vf format=yuv420p" .
    " -c:a aac -b:a 128k -movflags +faststart" .
    " " . escapeshellarg($finalPath) . " 2>&1";

exec($ffmpegCmd, $output, $returnCode);

if ($returnCode !== 0) {
    die("Error al optimizar video con FFmpeg.<br><pre>" . implode("\n", $output) . "</pre>");
}

// ================= SECCIÓN: Generar thumbnail (frame a los 5s) =================
$thumbCmd = escapeshellcmd(FFMPEG_PATH) . " -i " . escapeshellarg($finalPath) .
    " -ss 00:00:05 -vframes 1 -q:v 2 " . escapeshellarg($thumbPath);
exec($thumbCmd);

// Guardar en BD
$stmt = $pdo->prepare("INSERT INTO videos (title, filename, thumbnail, upload_date) VALUES (?, ?, ?, NOW())");
$stmt->execute([$title, $filename, $thumbName]);

header("Location: videos.php?msg=Video+subido+correctamente");
exit;
