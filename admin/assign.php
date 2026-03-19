<?php
session_start();
require_once '../config.php';

$errors = [];
$flash = null;  // Para mensajes de éxito o error en la misma página

$client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;

if ($client_id <= 0) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Cliente no especificado'];
    header("Location: clientes.php");
    exit;
}

// Cargar datos del cliente
$stmt = $pdo->prepare("SELECT name, playlist_version FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch();
if (!$client) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Cliente no encontrado'];
    header("Location: clientes.php");
    exit;
}

// Todos los videos
$videos = $pdo->query("
SELECT id, title, filename, upload_date
FROM videos
ORDER BY upload_date DESC, title ASC
")->fetchAll();

// Videos ya asignados a este cliente + su orden
$assigned = [];
$stmt = $pdo->prepare("
SELECT video_id, play_order
FROM video_client
WHERE client_id = ?
ORDER BY play_order ASC
");
$stmt->execute([$client_id]);
while ($row = $stmt->fetch()) {
    $assigned[$row['video_id']] = $row['play_order'];
}

// Procesar guardado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_videos = $_POST['videos'] ?? [];
    $selected_videos = array_map('intval', $selected_videos);

    try {
        $pdo->beginTransaction();

        // Borrar asignaciones existentes
        $pdo->prepare("DELETE FROM video_client WHERE client_id = ?")->execute([$client_id]);

        // Insertar nuevas con orden secuencial
        $order = 1;
        foreach ($selected_videos as $video_id) {
            $pdo->prepare("
            INSERT INTO video_client (video_id, client_id, play_order)
            VALUES (?, ?, ?)
            ")->execute([$video_id, $client_id, $order++]);
        }

        // Incrementar versión de playlist
        $pdo->prepare("UPDATE clients SET playlist_version = playlist_version + 1 WHERE id = ?")
        ->execute([$client_id]);

        $pdo->commit();

        // Mensaje flash en la misma página
        $flash = [
            'type' => 'success',
            'message' => 'Playlist actualizada correctamente. Versión actual: ' . ($client['playlist_version'] + 1)
        ];

    } catch (Exception $e) {
        $pdo->rollBack();
        $flash = [
            'type' => 'danger',
            'message' => 'Error al guardar las asignaciones: ' . $e->getMessage()
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Asignar videos a <?= htmlspecialchars($client['name']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-4">

<h2>Asignar videos a: <strong><?= htmlspecialchars($client['name']) ?></strong></h2>
<p class="text-muted mb-4">
Versión actual de la playlist: <strong><?= $client['playlist_version'] ?></strong><br>
Selecciona los videos que deseas incluir. El orden será el de aparición en la tabla (de arriba hacia abajo).
</p>

<?php if (isset($flash)): ?>
<div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
<?= htmlspecialchars($flash['message']) ?>
<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
<?php foreach ($errors as $err): ?>
<p class="mb-0"><?= htmlspecialchars($err) ?></p>
<?php endforeach; ?>
</div>
<?php endif; ?>

<form method="post">
<div class="table-responsive">
<table class="table table-hover align-middle">
<thead class="table-dark">
<tr>
<th style="width: 60px;">Incluir</th>
<th>Título</th>
<th>Archivo</th>
<th>Fecha carga</th>
</tr>
</thead>
<tbody>
<?php foreach ($videos as $video):
$checked = isset($assigned[$video['id']]) ? 'checked' : '';
?>
<tr>
<td class="text-center">
<input type="checkbox" name="videos[]" value="<?= $video['id'] ?>"
class="form-check-input fs-5" <?= $checked ?>>
</td>
<td><?= htmlspecialchars($video['title']) ?></td>
<td><code><?= htmlspecialchars($video['filename']) ?></code></td>
<td><?= date('d/m/Y H:i', strtotime($video['upload_date'])) ?></td>
</tr>
<?php endforeach; ?>

<?php if (empty($videos)): ?>
<tr><td colspan="4" class="text-center py-5 text-muted">No hay videos cargados aún</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>

<div class="d-flex justify-content-between mt-4 pt-3 border-top">
<a href="clientes.php" class="btn btn-secondary">Volver a lista de clientes</a>
<button type="submit" class="btn btn-primary px-5">Guardar cambios en playlist</button>
</div>
</form>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
