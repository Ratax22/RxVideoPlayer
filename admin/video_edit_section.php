<?php
// ================================================
// video_edit_section.php
// Editar video existente o crear nuevo (id=0)
// ================================================

$errors = [];
$video = [
    'id'          => 0,
    'title'       => '',
    'filename'    => '',
    'thumbnail'   => '',
    'upload_date' => date('Y-m-d H:i:s')
];

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ================================================
// CARGAR DATOS SI ES EDICIÓN + CHEQUEO PERMISO
// ================================================
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT id, title, filename, thumbnail, upload_date FROM videos WHERE id = ?");
    $stmt->execute([$id]);
    $video = $stmt->fetch(PDO::FETCH_ASSOC) ?: $video;

    if (!$video['id']) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Video no encontrado.'];
        header("Location: ?action=videos");
        exit;
    }

    // Chequear permiso
    $sucursales_usuario = getSucursalesAcceso($pdo, $_SESSION['usuario_id'], $_SESSION['rol']);
    $stmt = $pdo->prepare("SELECT sucursal_id FROM video_sucursal WHERE video_id = ?");
    $stmt->execute([$id]);
    $sucursales_video = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $tiene_acceso = $_SESSION['rol'] === 'admin' || 
                    array_intersect($sucursales_usuario, $sucursales_video) ||
                    empty($sucursales_video);

    if (!$tiene_acceso) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'No tienes permiso para editar este video.'];
        header("Location: ?action=videos");
        exit;
    }

    // Sucursales asignadas actuales
    $sucursales_asignadas = $sucursales_video;
}

// Sucursales que el usuario puede asignar
$sucursales = [];
$sucursales_ids_permitidas = getSucursalesAcceso($pdo, $_SESSION['usuario_id'], $_SESSION['rol']);

if ($_SESSION['rol'] === 'admin') {
    $sucursales = $pdo->query("
        SELECT s.id, s.nombre, e.nombre AS empresa 
        FROM sucursales s 
        INNER JOIN empresas e ON s.empresa_id = e.id 
        WHERE s.activo = 1 
        ORDER BY e.nombre, s.nombre
    ")->fetchAll(PDO::FETCH_ASSOC);
} elseif (!empty($sucursales_ids_permitidas)) {
    $placeholders = implode(',', array_fill(0, count($sucursales_ids_permitidas), '?'));
    $stmt = $pdo->prepare("
        SELECT s.id, s.nombre, e.nombre AS empresa 
        FROM sucursales s 
        INNER JOIN empresas e ON s.empresa_id = e.id 
        WHERE s.id IN ($placeholders) AND s.activo = 1 
        ORDER BY e.nombre, s.nombre
    ");
    $stmt->execute($sucursales_ids_permitidas);
    $sucursales = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ================================================
// PROCESAR FORMULARIO (POST)
// ================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id             = (int)($_POST['id'] ?? 0);
    $title          = trim($_POST['title'] ?? '');
    $rotate         = (int)($_POST['rotate'] ?? 0);
    $sucursales_post = $_POST['sucursales'] ?? [];

    if (empty($title)) {
        $errors[] = "El título es obligatorio.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Actualizar título
            $stmt = $pdo->prepare("UPDATE videos SET title = ? WHERE id = ?");
            $stmt->execute([$title, $id]);

            // Reprocesar video si hay rotación o pedido de regenerar thumbnail
            if ($rotate > 0 || isset($_POST['regenerate_thumb'])) {
                $ffmpeg = FFMPEG_PATH;
                $video_path = VIDEO_DIR . $video['filename'];
                $new_video_temp = VIDEO_DIR . 'temp_' . $video['filename'];
                $thumb_path = THUMB_DIR . pathinfo($video['filename'], PATHINFO_FILENAME) . '.jpg';

                $vf = '';
                if ($rotate === 90) $vf = 'transpose=1';
                elseif ($rotate === 180) $vf = 'transpose=2';
                elseif ($rotate === 270) $vf = 'transpose=3';

                $cmd = escapeshellcmd("$ffmpeg -i " . escapeshellarg($video_path));
                if ($vf) $cmd .= " -vf $vf";
                $cmd .= " -c:v libx264 -preset medium -crf 23 -c:a aac -b:a 128k -movflags +faststart";
                $cmd .= " " . escapeshellarg($new_video_temp) . " 2>&1";

                exec($cmd, $output, $return_var);

                if ($return_var === 0) {
                    rename($new_video_temp, $video_path);
                    exec(escapeshellcmd("$ffmpeg -i " . escapeshellarg($video_path) . " -ss 00:00:05 -vframes 1 " . escapeshellarg($thumb_path)));
                } else {
                    $errors[] = "Error al reprocesar: " . implode("\n", $output);
                }
            }

            // Actualizar asignaciones de sucursales
            $pdo->prepare("DELETE FROM video_sucursal WHERE video_id = ?")->execute([$id]);

            $sucursales_validas = array_intersect($sucursales_post, $sucursales_ids_permitidas);
            if (!empty($sucursales_validas)) {
                $stmt = $pdo->prepare("INSERT IGNORE INTO video_sucursal (video_id, sucursal_id) VALUES (?, ?)");
                foreach ($sucursales_validas as $suc_id) {
                    $stmt->execute([$id, (int)$suc_id]);
                }
            }

            $pdo->commit();
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Video actualizado correctamente'];
            header("Location: ?action=videos");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Error: " . $e->getMessage();
        }
    }
}
?>

<h2 class="mb-4">Editar Video: <?= htmlspecialchars($video['title']) ?></h2>

<div class="row mb-5">
    <div class="col-md-4 text-center">
        <?php if (!empty($video['thumbnail'])): ?>
            <img src="../images/thumbs/<?= htmlspecialchars($video['thumbnail']) ?>" 
                 alt="Thumbnail" class="img-fluid rounded shadow mb-3" 
                 style="max-width: 200px; cursor: pointer;" 
                 data-bs-toggle="modal" data-bs-target="#videoModal"
                 onclick="document.getElementById('videoPlayer').src = '../videos/<?= htmlspecialchars($video['filename']) ?>'">
            <div class="form-text text-muted">Clic para reproducir</div>
        <?php else: ?>
            <div class="alert alert-warning p-3">Sin thumbnail disponible</div>
        <?php endif; ?>
    </div>
    <div class="col-md-8">
        <p><strong>Título actual:</strong> <?= htmlspecialchars($video['title']) ?></p>
        <p><strong>Archivo:</strong> <?= htmlspecialchars($video['filename']) ?></p>
        <p><strong>Fecha subida:</strong> <?= date('d/m/Y H:i', strtotime($video['upload_date'])) ?></p>
    </div>
</div>

<form method="post" action="?action=videos">
    <input type="hidden" name="id" value="<?= $video['id'] ?>">

    <div class="mb-3">
        <label class="form-label fw-bold">Título del video</label>
        <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($video['title']) ?>" required>
    </div>

    <div class="mb-3">
        <label class="form-label fw-bold">Rotación (reprocesar video)</label>
        <select name="rotate" class="form-select">
            <option value="0">No rotar</option>
            <option value="90">Rotar 90°</option>
            <option value="180">Rotar 180°</option>
            <option value="270">Rotar 270°</option>
        </select>
        <div class="form-text">Esto reprocesa el video y genera nuevo thumbnail (puede tardar).</div>
    </div>

    <div class="mb-4">
        <label class="form-label fw-bold">Sucursales donde está disponible</label>
        <select name="sucursales[]" class="form-select" multiple size="8">
            <?php foreach ($sucursales as $s): 
                $selected = in_array($s['id'], $sucursales_asignadas) ? 'selected' : '';
            ?>
                <option value="<?= $s['id'] ?>" <?= $selected ?>>
                    <?= htmlspecialchars($s['empresa'] . ' → ' . $s['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div class="form-text">Solo puedes asignar/desasignar sucursales que tienes permiso de gestionar.</div>
    </div>

    <div class="d-flex gap-2 justify-content-end">
        <a href="?action=videos" class="btn btn-secondary">Cancelar</a>
        <button type="submit" class="btn btn-success px-4">Guardar Cambios</button>
    </div>
</form>

<!-- Modal para reproducir -->
<div class="modal fade" id="videoModal" tabindex="-1" aria-labelledby="videoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="videoModalLabel">Reproduciendo video</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <video id="videoPlayer" controls width="100%" style="max-height: 70vh;">
                    <source src="" type="video/mp4">
                    Tu navegador no soporta la reproducción de video.
                </video>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>