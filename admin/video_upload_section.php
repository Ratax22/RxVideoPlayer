<?php
// ================================================
// video_upload_section.php
// Subir nuevo video con asignación de sucursales
// ================================================

$errors = [];

// Sucursales que este usuario puede asignar (según rol y permisos)
$sucursales = [];
$sucursales_ids = getSucursalesAcceso($pdo, $_SESSION['usuario_id'], $_SESSION['rol']);

if ($_SESSION['rol'] === 'admin') {
    // Admin ve todas las sucursales activas
    $sucursales = $pdo->query("
        SELECT s.id, s.nombre, e.nombre AS empresa 
        FROM sucursales s 
        INNER JOIN empresas e ON s.empresa_id = e.id 
        WHERE s.activo = 1 
        ORDER BY e.nombre, s.nombre
    ")->fetchAll(PDO::FETCH_ASSOC);
} elseif (!empty($sucursales_ids)) {
    $placeholders = implode(',', array_fill(0, count($sucursales_ids), '?'));
    $stmt = $pdo->prepare("
        SELECT s.id, s.nombre, e.nombre AS empresa 
        FROM sucursales s 
        INNER JOIN empresas e ON s.empresa_id = e.id 
        WHERE s.id IN ($placeholders) AND s.activo = 1 
        ORDER BY e.nombre, s.nombre
    ");
    $stmt->execute($sucursales_ids);
    $sucursales = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Si no hay sucursales disponibles → error
if (empty($sucursales)) {
    $errors[] = "No tienes sucursales asignadas para subir videos. Contacta a un administrador.";
}

// Procesar subida
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
    $title       = trim($_POST['title'] ?? '');
    $sucursales_post = $_POST['sucursales'] ?? [];
    $rotate      = (int)($_POST['rotate'] ?? 0);

    $file        = $_FILES['video'];
    $filename    = time() . '_' . preg_replace('/[^a-zA-Z0-9\._-]/', '', basename($file['name']));
    $upload_path = ROOT_DIR . 'uploads/' . $filename;
    $final_path  = VIDEO_DIR . $filename;
    $thumb_path  = THUMB_DIR . pathinfo($filename, PATHINFO_FILENAME) . '.jpg';

    // Validaciones
    if (empty($title)) {
        $errors[] = "El título es obligatorio.";
    }
    if (empty($sucursales_post)) {
        $errors[] = "Debes asignar al menos una sucursal.";
    }

    if (empty($errors)) {
        // Mover archivo temporal
        if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
            $errors[] = "Error al mover el archivo subido.";
        } else {
            try {
                $pdo->beginTransaction();

                // Procesamiento FFmpeg
                $ffmpeg = FFMPEG_PATH;
                $cmd = escapeshellcmd("$ffmpeg -i " . escapeshellarg($upload_path));

                // Rotación
                $vf = '';
                if ($rotate === 90) $vf = 'transpose=1';
                elseif ($rotate === 180) $vf = 'transpose=2';
                elseif ($rotate === 270) $vf = 'transpose=3';

                if ($vf) $cmd .= " -vf $vf";
                $cmd .= " -c:v libx264 -preset medium -crf 23 -c:a aac -b:a 128k -movflags +faststart";
                $cmd .= " " . escapeshellarg($final_path) . " 2>&1";

                exec($cmd, $output, $return_var);

                if ($return_var !== 0) {
                    $errors[] = "Error al procesar con FFmpeg: " . implode("\n", $output);
                } else {
                    // Generar thumbnail
                    $thumb_cmd = escapeshellcmd("$ffmpeg -i " . escapeshellarg($final_path) . " -ss 00:00:05 -vframes 1 " . escapeshellarg($thumb_path));
                    exec($thumb_cmd);

                    $thumb_name = basename($thumb_path);

                    // Guardar en BD
                    $stmt = $pdo->prepare("
                        INSERT INTO videos (title, filename, thumbnail, upload_date) 
                        VALUES (?, ?, ?, NOW())
                    ");
                    $stmt->execute([$title, $filename, $thumb_name]);

                    $video_id = $pdo->lastInsertId();

                    // Asignar sucursales (solo las permitidas)
                    $sucursales_validas = array_intersect($sucursales_post, $sucursales_ids);
                    if (!empty($sucursales_validas)) {
                        $stmt = $pdo->prepare("INSERT INTO video_sucursal (video_id, sucursal_id) VALUES (?, ?)");
                        foreach ($sucursales_validas as $suc_id) {
                            $stmt->execute([$video_id, (int)$suc_id]);
                        }
                    }

                    unlink($upload_path); // limpiar temporal

                    $pdo->commit();
                    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Video subido, procesado y asignado correctamente.'];
                    header("Location: ?action=videos");
                    exit;
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = "Error en base de datos o procesamiento: " . $e->getMessage();
                if (file_exists($upload_path)) unlink($upload_path);
                if (file_exists($final_path)) unlink($final_path);
                if (file_exists($thumb_path)) unlink($thumb_path);
            }
        }
    }
}
?>

<h2 class="mb-4">Subir Nuevo Video</h2>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <ul class="mb-0">
        <?php foreach ($errors as $err): ?>
            <li><?= htmlspecialchars($err) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if (empty($sucursales)): ?>
<div class="alert alert-warning">
    No tienes sucursales asignadas para subir videos. Contacta a un administrador.
</div>
<?php else: ?>
<form method="post" enctype="multipart/form-data" action="?action=videos">
    <div class="mb-4">
        <label class="form-label fw-bold">Archivo de video</label>
        <input type="file" name="video" accept="video/*" class="form-control" required>
    </div>

    <div class="mb-4">
        <label class="form-label fw-bold">Título del video</label>
        <input type="text" name="title" class="form-control" required placeholder="Ej: Promo Verano 2025">
    </div>

    <div class="mb-4">
        <label class="form-label fw-bold">Rotación (opcional)</label>
        <select name="rotate" class="form-select">
            <option value="0">Sin rotación</option>
            <option value="90">Rotar 90° (sentido horario)</option>
            <option value="180">Rotar 180°</option>
            <option value="270">Rotar 270° (sentido antihorario)</option>
        </select>
    </div>

    <div class="mb-4">
        <label class="form-label fw-bold">Sucursales donde estará disponible</label>
        <select name="sucursales[]" class="form-select" multiple size="8" required>
            <?php foreach ($sucursales as $s): ?>
                <option value="<?= $s['id'] ?>">
                    <?= htmlspecialchars($s['empresa'] . ' → ' . $s['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div class="form-text text-muted">Mantén Ctrl/Cmd para seleccionar varias</div>
    </div>

    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
        <a href="?action=videos" class="btn btn-secondary">Cancelar</a>
        <button type="submit" class="btn btn-success px-5">Subir y Procesar</button>
    </div>
</form>
<?php endif; ?>