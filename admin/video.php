<?php
// ================================================
// admin/videos.php - Listado y subida de videos
// ================================================
require_once 'proteccion.php';
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['video'])) {
    // Procesar subida → se mueve a video_upload.php o inline, pero por claridad lo separamos después
    // Por ahora solo mostramos el form
}

// ================= SECCIÓN: Listado de videos =================
$stmt = $pdo->query("SELECT * FROM videos ORDER BY upload_date DESC");
$videos = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Admin - Videos | videoplayer.ratax.com.ar</title>
<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
h1 { color: #333; }
table { width: 100%; border-collapse: collapse; margin-top: 20px; background: white; }
th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
th { background: #007bff; color: white; }
img.thumb { max-width: 160px; border-radius: 4px; }
.form-upload { background: white; padding: 20px; border: 1px solid #ccc; border-radius: 8px; margin-bottom: 30px; }
button { padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; }
</style>
</head>
<body>

<h1>Gestión de Videos</h1>

<div class="form-upload">
<h2>Subir nuevo video</h2>
<form action="video_upload.php" method="post" enctype="multipart/form-data">
<label>Título del video:</label><br>
<input type="text" name="title" required style="width:100%; padding:8px; margin:8px 0;"><br><br>

<input type="file" name="video" accept="video/*" required><br><br>
<button type="submit">Subir, optimizar y guardar</button>
</form>
</div>

<h2>Videos cargados (<?= count($videos) ?>)</h2>

<?php if (empty($videos)): ?>
<p>No hay videos aún.</p>
<?php else: ?>
<table>
<tr>
<th>Miniatura</th>
<th>Título</th>
<th>Fecha de carga</th>
<th>Archivo</th>
<th>Acciones</th>
</tr>
<?php foreach ($videos as $v): ?>
<tr>
<td><img src="../images/thumbs/<?= htmlspecialchars($v['thumbnail']) ?>" class="thumb" alt="thumb"></td>
<td><?= htmlspecialchars($v['title']) ?></td>
<td><?= date('d/m/Y H:i', strtotime($v['upload_date'])) ?></td>
<td><?= htmlspecialchars($v['filename']) ?></td>
<td>
<a href="video_edit.php?id=<?= $v['id'] ?>">Editar / Rotar</a> |
<a href="video_delete.php?id=<?= $v['id'] ?>" onclick="return confirm('¿Eliminar este video definitivamente?');">Eliminar</a>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

<p><a href="index.php">← Volver al menú principal</a></p>

</body>
</html>
