<?php
// ================================================
// admin/video_edit.php - Rotar y previsualizar
// ================================================
require_once 'proteccion.php';
require_once '../config.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM videos WHERE id = ?");
$stmt->execute([$id]);
$video = $stmt->fetch();

if (!$video) die("Video no encontrado.");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rotate'])) {
    $degrees = (int)$_POST['degrees'];

    $input  = VIDEO_DIR . $video['filename'];
    $output = $input . '.tmp.mp4'; // temporal

    $filter = '';
    switch ($degrees) {
        case 90:  $filter = "transpose=1"; break;        // clockwise
        case 180: $filter = "rotate=PI"; break;
        case 270: $filter = "transpose=2"; break;        // counterclockwise
    }

    if ($filter) {
        $cmd = escapeshellcmd(FFMPEG_PATH) . " -i " . escapeshellarg($input) .
               " -vf " . escapeshellarg($filter) .
               " -c:v libx264 -preset medium -crf 23 -c:a aac" .
               " " . escapeshellarg($output) . " 2>&1";
        exec($cmd, $out, $ret);

        if ($ret === 0) {
            rename($output, $input); // reemplaza original

            // Nueva thumbnail
            $newThumb = time() . '_thumb.jpg';
            $thumbCmd = escapeshellcmd(FFMPEG_PATH) . " -i " . escapeshellarg($input) .
                        " -ss 00:00:05 -vframes 1 -q:v 2 " . escapeshellarg(THUMB_DIR . $newThumb);
            exec($thumbCmd);

            $pdo->prepare("UPDATE videos SET thumbnail = ? WHERE id = ?")->execute([$newThumb, $id]);
        } else {
            @unlink($output);
            echo "<pre>Error rotación:\n" . implode("\n", $out) . "</pre>";
        }
    }
    header("Location: video_edit.php?id=$id&ok=1");
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar: <?= htmlspecialchars($video['title']) ?></title>
    <style>video { max-width: 640px; border: 3px solid #333; }</style>
</head>
<body>
<h1>Editar video: <?= htmlspecialchars($video['title']) ?></h1>

<video controls>
    <source src="../videos/<?= htmlspecialchars($video['filename']) ?>" type="video/mp4">
</video>

<p>Fecha: <?= date('d/m/Y H:i', strtotime($video['upload_date'])) ?></p>
<img src="../images/thumbs/<?= htmlspecialchars($video['thumbnail']) ?>" style="max-width:200px;">

<h2>Rotar video (re-procesa el archivo)</h2>
<form method="post">
    <select name="degrees">
        <option value="0">No rotar</option>
        <option value="90">90° horario</option>
        <option value="180">180°</option>
        <option value="270">90° antihorario (270°)</option>
    </select>
    <button type="submit" name="rotate">Aplicar rotación</button>
</form>

<?php if (isset($_GET['ok'])): ?>
    <p style="color:green;">¡Rotación aplicada! Refresca para ver cambios.</p>
<?php endif; ?>

<p><a href="video.php">← Volver a videos</a></p>
</body>
</html>
