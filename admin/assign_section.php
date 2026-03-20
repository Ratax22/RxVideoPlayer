<?php
// ================================================
// assign_section.php - Asignación múltiple de videos a cliente
// ================================================

$client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;

if ($client_id <= 0) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Cliente no especificado.'];
    header("Location: ?action=clientes");
    exit;
}

// Cargar datos del cliente
$stmt = $pdo->prepare("SELECT name FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch();
if (!$client) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Cliente no encontrado.'];
    header("Location: ?action=clientes");
    exit;
}

// Todos los videos
$videos = $pdo->query("
    SELECT id, title, filename, upload_date 
    FROM videos 
    ORDER BY upload_date DESC, title ASC
")->fetchAll();

// Videos ya asignados a este cliente
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

        $pdo->prepare("DELETE FROM video_client WHERE client_id = ?")->execute([$client_id]);

        $order = 1;
        foreach ($selected_videos as $video_id) {
            $pdo->prepare("
                INSERT INTO video_client (video_id, client_id, play_order) 
                VALUES (?, ?, ?)
            ")->execute([$video_id, $client_id, $order++]);
        }

        $pdo->prepare("UPDATE clients SET playlist_version = playlist_version + 1 WHERE id = ?")
            ->execute([$client_id]);

        $pdo->commit();

        $_SESSION['flash'] = [
            'type' => 'success',
            'message' => 'Playlist actualizada (versión ahora: ' . ($client['playlist_version'] + 1) . ')'
        ];
        header("Location: ?action=clientes");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $errors[] = "Error al guardar: " . $e->getMessage();
    }
}
?>

<h2>Asignar videos a: <strong><?= htmlspecialchars($client['name']) ?></strong></h2>
<p class="text-muted">Selecciona los videos que deseas incluir. El orden será el de selección.</p>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <?php foreach ($errors as $err): ?>
        <p class="mb-0"><?= htmlspecialchars($err) ?></p>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<form method="post">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Incluir</th>
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
                        <input type="checkbox" name="videos[]" value="<?= $video['id'] ?>" <?= $checked ?>>
                    </td>
                    <td><?= htmlspecialchars($video['title']) ?></td>
                    <td><code><?= htmlspecialchars($video['filename']) ?></code></td>
                    <td><?= date('d/m/Y H:i', strtotime($video['upload_date'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-between mt-4">
        <a href="?action=clientes" class="btn btn-secondary">Volver</a>
        <button type="submit" class="btn btn-primary px-5">Guardar Playlist</button>
    </div>
</form>