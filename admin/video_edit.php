<?php
// ================================================
// SECCIÓN 1: INICIO Y PROTECCIÓN
// ================================================
session_start();
require_once '../config.php';
require_once 'proteccion.php';

// ================================================
// SECCIÓN 2: OBTENER VIDEO Y CHEQUEAR PERMISO
// ================================================
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'ID de video inválido.'];
    header("Location: video.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM videos WHERE id = ?");
$stmt->execute([$id]);
$video = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$video) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Video no encontrado.'];
    header("Location: video.php");
    exit;
}

// Chequear permiso: admin o tiene acceso vía sucursal
$sucursales_usuario = getSucursalesAcceso($pdo, $_SESSION['usuario_id'], $_SESSION['rol']);
$stmt = $pdo->prepare("SELECT sucursal_id FROM video_sucursal WHERE video_id = ?");
$stmt->execute([$id]);
$sucursales_video = $stmt->fetchAll(PDO::FETCH_COLUMN);

$tiene_acceso = $_SESSION['rol'] === 'admin' || 
                array_intersect($sucursales_usuario, $sucursales_video) ||
                empty($sucursales_video); // si no tiene asignación, se permite (video global)

if (!$tiene_acceso) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'No tienes permiso para editar este video.'];
    header("Location: video.php");
    exit;
}

// Sucursales actuales del video
$sucursales_asignadas = $sucursales_video;

// Sucursales que el usuario puede asignar
$sucursales = [];
$sucursales_ids_permitidas = $sucursales_usuario;

if ($_SESSION['rol'] === 'admin') {
    $sucursales = $pdo->query("
        SELECT s.id, s.nombre, e.nombre AS empresa 
        FROM sucursales s 
        INNER JOIN empresas e ON s.empresa_id = e.id 
        WHERE s.activo = 1 
        ORDER BY e.nombre, s.nombre
    ")->fetchAll(PDO::FETCH_ASSOC);
} else {
    if (!empty($sucursales_ids_permitidas)) {
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
}

// ================================================
// SECCIÓN 3: PROCESAR FORMULARIO (POST)
// ================================================
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title'] ?? '');
    $rotate      = (int)($_POST['rotate'] ?? 0);
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

            // Si se pide nueva rotación o regenerar thumbnail
            if ($rotate > 0 || isset($_POST['regenerate_thumb'])) {
                $ffmpeg = FFMPEG_PATH;
                $video_path = VIDEO_DIR . $video['filename'];
                $new_video_temp = VIDEO_DIR . 'temp_' . $video['filename'];
                $thumb_path = THUMB_DIR . pathinfo($video['filename'], PATHINFO_FILENAME) . '.jpg';

                // Rotación
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
                    // Reemplazar original
                    rename($new_video_temp, $video_path);

                    // Regenerar thumbnail
                    $thumb_cmd = escapeshellcmd("$ffmpeg -i " . escapeshellarg($video_path) . " -ss 00:00:05 -vframes 1 " . escapeshellarg($thumb_path));
                    exec($thumb_cmd);
                } else {
                    $errors[] = "Error al reprocesar video: " . implode("\n", $output);
                }
            }

            // Actualizar asignaciones de sucursales (solo las permitidas)
            $pdo->prepare("DELETE FROM video_sucursal WHERE video_id = ?")->execute([$id]);

            $sucursales_validas = array_intersect($sucursales_post, $sucursales_ids_permitidas);
            if (!empty($sucursales_validas)) {
                $stmt = $pdo->prepare("INSERT INTO video_sucursal (video_id, sucursal_id) VALUES (?, ?)");
                foreach ($sucursales_validas as $suc_id) {
                    $stmt->execute([$id, (int)$suc_id]);
                }
            }

            $pdo->commit();
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Video actualizado correctamente'];
            header("Location: video.php");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Video</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-5" style="max-width: 800px;">

    <h2 class="mb-4">Editar Video: <?= htmlspecialchars($video['title']) ?></h2>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $err): ?>
                <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-md-6">
            <?php if (!empty($video['thumbnail'])): ?>
                <img src="../images/thumbs/<?= htmlspecialchars($video['thumbnail']) ?>" 
                     alt="Thumbnail" class="img-fluid rounded shadow">
            <?php else: ?>
                <div class="alert alert-warning">Sin thumbnail disponible</div>
            <?php endif; ?>
        </div>
        <div class="col-md-6">
            <p><strong>Título actual:</strong> <?= htmlspecialchars($video['title']) ?></p>
            <p><strong>Archivo:</strong> <?= htmlspecialchars($video['filename']) ?></p>
            <p><strong>Fecha subida:</strong> <?= date('d/m/Y H:i', strtotime($video['upload_date'])) ?></p>
        </div>
    </div>

    <form method="post">
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
            <div class="form-text">Esto reprocesa el video y genera nuevo thumbnail.</div>
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
            <a href="video.php" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-success px-4">Guardar Cambios</button>
        </div>
    </form>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>