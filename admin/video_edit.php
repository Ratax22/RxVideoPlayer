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
    header("Location: videos.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM videos WHERE id = ?");
$stmt->execute([$id]);
$video = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$video) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Video no encontrado.'];
    header("Location: videos.php");
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
    header("Location: videos.php");
    exit;
}

// Sucursales actuales
$sucursales_asignadas = $sucursales_video;

// Sucursales disponibles para asignar
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Video</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .thumb-preview {
            max-width: 200px;
            cursor: pointer;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            transition: transform 0.2s;
        }
        .thumb-preview:hover {
            transform: scale(1.05);
        }
    </style>
</head>
<body class="bg-light">

<!-- Navbar superior -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">Panel Publicidad</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">Salir (<?= htmlspecialchars($_SESSION['nombre'] ?? 'Usuario') ?>)</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container py-5" style="max-width: 900px;">

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

    <div class="row mb-5">
        <div class="col-md-4 text-center">
            <?php if (!empty($video['thumbnail'])): ?>
                <img src="../images/thumbs/<?= htmlspecialchars($video['thumbnail']) ?>" 
                     alt="Thumbnail" class="thumb-preview img-fluid mb-3" 
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
            <div class="form-text">Esto reprocesa el video y genera nuevo thumbnail (puede tardar unos segundos).</div>
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
            <a href="videos.php" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-success px-4">Guardar Cambios</button>
        </div>
    </form>

</div>

<!-- Modal para reproducir video -->
<div class="modal fade" id="videoModal" tabindex="-1" aria-labelledby="videoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="videoModalLabel">Reproduciendo: <?= htmlspecialchars($video['title']) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <video id="videoPlayer" controls width="100%" style="max-height: 70vh;">
                    <source src="../videos/<?= htmlspecialchars($video['filename']) ?>" type="video/mp4">
                    Tu navegador no soporta la reproducción de video.
                </video>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>