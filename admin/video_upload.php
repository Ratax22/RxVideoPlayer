<?php
// ================================================
// SECCIÓN 1: INICIO Y PROTECCIÓN
// ================================================
session_start();
require_once '../config.php';
require_once 'proteccion.php';

// ================================================
// SECCIÓN 2: CHEQUEO DE PERMISOS PARA SUBIR
// ================================================
$rol = $_SESSION['rol'];
if ($rol === 'empleado') {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'No tienes permiso para subir videos.'];
    header("Location: video.php");
    exit;
}

// ================================================
// SECCIÓN 3: SUCURSALES QUE PUEDE ASIGNAR ESTE USUARIO
// ================================================
$sucursales = [];
$sucursales_ids = getSucursalesAcceso($pdo, $_SESSION['usuario_id'], $rol);

if (!empty($sucursales_ids)) {
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
} elseif ($rol === 'admin') {
    $sucursales = $pdo->query("
        SELECT s.id, s.nombre, e.nombre AS empresa 
        FROM sucursales s 
        INNER JOIN empresas e ON s.empresa_id = e.id 
        WHERE s.activo = 1 
        ORDER BY e.nombre, s.nombre
    ")->fetchAll(PDO::FETCH_ASSOC);
}

// ================================================
// SECCIÓN 4: PROCESAR SUBIDA Y PROCESAMIENTO
// ================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
    $title       = trim($_POST['title'] ?? '');
    $sucursales_post = $_POST['sucursales'] ?? [];
    $rotate      = (int)($_POST['rotate'] ?? 0); // 0, 90, 180, 270

    $file        = $_FILES['video'];
    $filename    = time() . '_' . preg_replace('/[^a-zA-Z0-9\._-]/', '', basename($file['name']));
    $upload_path = ROOT_DIR . 'uploads/' . $filename;
    $final_path  = VIDEO_DIR . $filename;
    $thumb_path  = THUMB_DIR . pathinfo($filename, PATHINFO_FILENAME) . '.jpg';

    $errors = [];

    // Validaciones básicas
    if (empty($title)) {
        $errors[] = "El título es obligatorio.";
    }
    if (empty($sucursales_post)) {
        $errors[] = "Debes asignar al menos una sucursal.";
    }

    // Mover archivo temporal
    if (empty($errors) && !move_uploaded_file($file['tmp_name'], $upload_path)) {
        $errors[] = "Error al mover el archivo subido.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // ================================================
            // Procesamiento con FFmpeg
            // ================================================
            $ffmpeg = FFMPEG_PATH;
            $cmd = escapeshellcmd("$ffmpeg -i " . escapeshellarg($upload_path));

            // Rotación si corresponde
            if ($rotate === 90) {
                $cmd .= " -vf transpose=1";
            } elseif ($rotate === 180) {
                $cmd .= " -vf transpose=2";
            } elseif ($rotate === 270) {
                $cmd .= " -vf transpose=3";
            }

            // Convertir a mp4 H.264, optimizado
            $cmd .= " -c:v libx264 -preset medium -crf 23 -c:a aac -b:a 128k -movflags +faststart";
            $cmd .= " " . escapeshellarg($final_path) . " 2>&1";

            exec($cmd, $output, $return_var);

            if ($return_var !== 0) {
                $errors[] = "Error al procesar con FFmpeg: " . implode("\n", $output);
            } else {
                // Generar thumbnail (frame al 5%)
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

                // Asignar sucursales
                $stmt = $pdo->prepare("INSERT INTO video_sucursal (video_id, sucursal_id) VALUES (?, ?)");
                foreach ($sucursales_post as $suc_id) {
                    $stmt->execute([$video_id, (int)$suc_id]);
                }

                // Limpiar archivo temporal
                unlink($upload_path);

                $pdo->commit();
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Video subido, procesado y asignado correctamente.'];
                header("Location: video.php");
                exit;
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Error en base de datos o procesamiento: " . $e->getMessage();
            // Limpiar archivos si falló
            if (file_exists($upload_path)) unlink($upload_path);
            if (file_exists($final_path)) unlink($final_path);
            if (file_exists($thumb_path)) unlink($thumb_path);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subir Video</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-5" style="max-width: 800px;">

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

    <form method="post" enctype="multipart/form-data">
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
            <a href="video.php" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-success px-5">Subir y Procesar</button>
        </div>
    </form>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>