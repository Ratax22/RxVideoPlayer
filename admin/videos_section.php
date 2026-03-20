<h1 class="mb-4">Videos Disponibles</h1>

<?php if (isset($_SESSION['flash'])): ?>
<div class="alert alert-<?= $_SESSION['flash']['type'] ?? 'info' ?> alert-dismissible fade show">
    <?= htmlspecialchars($_SESSION['flash']['message'] ?? '') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['flash']); endif; ?>

<?php
$sucursales_ids = getSucursalesAcceso($pdo, $_SESSION['usuario_id'], $_SESSION['rol']);

$videos = [];
if ($_SESSION['rol'] === 'admin') {
    $stmt = $pdo->query("SELECT * FROM videos ORDER BY upload_date DESC");
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$flash_nuevos = false;
foreach ($videos as $v) {
    if (strtotime($v['upload_date']) > strtotime('-7 days')) {
        $flash_nuevos = true;
        break;
    }
}
?>

<?php if ($flash_nuevos): ?>
<div class="alert alert-info alert-dismissible fade show">
    <strong>¡Nuevos videos disponibles!</strong> Hay contenido reciente que puedes usar en tus playlists.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($_SESSION['rol'] !== 'empleado'): ?>
<a href="?action=video_upload" class="btn btn-primary mb-3">+ Subir nuevo video</a>
<?php endif; ?>

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
                             alt="Thumbnail" class="img-thumbnail" style="width:80px; height:45px; object-fit:cover; cursor:pointer;"
                             data-bs-toggle="modal" data-bs-target="#videoModal"
                             onclick="document.getElementById('videoPlayer').src = '../videos/<?= htmlspecialchars($v['filename']) ?>'">
                    <?php else: ?>
                        <span class="text-muted">Sin thumb</span>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($v['title']) ?></td>
                <td><?= date('d/m/Y H:i', strtotime($v['upload_date'])) ?></td>
                <td>
                    <a href="?action=video_edit&id=<?= $v['id'] ?>" class="btn btn-sm btn-outline-warning">Editar</a>
                    <?php if ($_SESSION['rol'] === 'admin' || $_SESSION['rol'] === 'dueño'): ?>
                        <a href="?action=video_delete&id=<?= $v['id'] ?>" 
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

<!-- Modal para reproducir video -->
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