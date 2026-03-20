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