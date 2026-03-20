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