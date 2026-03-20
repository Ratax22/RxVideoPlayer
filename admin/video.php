<?php
session_start();
require_once '../config.php';
require_once 'proteccion.php';

// Sucursales accesibles
$sucursales_ids = getSucursalesAcceso($pdo, $_SESSION['usuario_id'], $_SESSION['rol']);

// Cargar videos según rol
$videos = [];
if ($_SESSION['rol'] === 'admin') {
    $stmt = $pdo->query("SELECT * FROM videos ORDER BY upload_date DESC");
} else {
    if (empty($sucursales_ids)) {
        $videos = [];
    } else {
        $placeholders = implode(',', array_fill(0, count($sucursales_ids), '?'));
        $stmt = $pdo->prepare("
            SELECT DISTINCT v.* 
            FROM videos v
            INNER JOIN video_sucursal vs ON v.id = vs.video_id
            WHERE vs.sucursal_id IN ($placeholders)
            ORDER BY v.upload_date DESC
        ");
        $stmt->execute($sucursales_ids);
    }
}
$videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Flash de videos nuevos (últimos 7 días)
$flash_nuevos = false;
foreach ($videos as $v) {
    if (strtotime($v['upload_date']) > strtotime('-7 days')) {
        $flash_nuevos = true;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Videos Disponibles</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .thumb-img {
            width: 80px;
            height: 45px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">Panel</a>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="logout.php">Salir</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container py-4">

    <?php if ($flash_nuevos): ?>
    <div class="alert alert-info alert-dismissible fade show">
        <strong>¡Nuevos videos disponibles!</strong> Hay contenido reciente que puedes usar en tus playlists.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Videos Disponibles</h1>
        <?php if ($_SESSION['rol'] !== 'empleado'): ?>
            <a href="video_upload.php" class="btn btn-primary">+ Subir nuevo video</a>
        <?php endif; ?>
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-bordered align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Thumbnail</th>
                    <th>Título</th>
                    <th>Fecha subida</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($videos as $v): ?>
                <tr>
                    <td>
                        <?php if (!empty($v['thumbnail'])): ?>
                            <img src="../images/thumbs/<?= htmlspecialchars($v['thumbnail']) ?>" 
                                 alt="Thumbnail" class="thumb-img">
                        <?php else: ?>
                            <span class="text-muted">Sin thumb</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($v['title']) ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($v['upload_date'])) ?></td>
                    <td>
                        <a href="video_edit.php?id=<?= $v['id'] ?>" class="btn btn-sm btn-outline-warning">Editar</a>
                        <?php if ($_SESSION['rol'] === 'admin' || $_SESSION['rol'] === 'dueño'): ?>
                            <a href="video_delete.php?id=<?= $v['id'] ?>" 
                               onclick="return confirm('¿Eliminar este video?')"
                               class="btn btn-sm btn-outline-danger">Eliminar</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($videos)): ?>
                <tr><td colspan="4" class="text-center py-4">No hay videos disponibles para tus sucursales</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>