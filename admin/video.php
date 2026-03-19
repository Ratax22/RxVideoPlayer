<?php
session_start();
require_once '../config.php';
require_once 'proteccion.php';

// Sucursales que puede ver el usuario actual
$sucursales_ids = getSucursalesAcceso($pdo, $_SESSION['usuario_id'], $_SESSION['rol']);

// Videos disponibles para este usuario
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
    <title>Videos - Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-4">
    <h1>Videos Disponibles</h1>

    <?php if ($flash_nuevos): ?>
    <div class="alert alert-info alert-dismissible fade show">
        <strong>¡Nuevos videos disponibles!</strong> Revisa la lista, hay contenido reciente que puedes usar en tus playlists.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <a href="video_upload.php" class="btn btn-primary mb-3">+ Subir nuevo video</a>

    <table class="table table-hover">
        <thead class="table-dark">
            <tr>
                <th>Título</th>
                <th>Fecha</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($videos as $v): ?>
            <tr>
                <td><?= htmlspecialchars($v['title']) ?></td>
                <td><?= date('d/m/Y H:i', strtotime($v['upload_date'])) ?></td>
                <td>
                    <a href="video_edit.php?id=<?= $v['id'] ?>" class="btn btn-sm btn-warning">Editar</a>
                    <a href="video_delete.php?id=<?= $v['id'] ?>" onclick="return confirm('¿Eliminar video?')" class="btn btn-sm btn-danger">Eliminar</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>