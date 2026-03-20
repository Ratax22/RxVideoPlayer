<h2 class="mb-4"><?= $id ? 'Editar Usuario' : 'Crear Nuevo Usuario' ?></h2>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <ul class="mb-0">
        <?php foreach ($errors as $err): ?>
            <li><?= htmlspecialchars($err) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<form method="post" action="?action=usuarios">
    <input type="hidden" name="id" value="<?= $id ?>">

    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label fw-bold">Email</label>
            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($usuario['email']) ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-bold">Nombre completo</label>
            <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($usuario['nombre']) ?>" required>
        </div>
    </div>

    <?php if ($id === 0 || !empty($password)): ?>
    <div class="mt-3">
        <label class="form-label fw-bold">Contraseña <?= $id > 0 ? '(dejar vacío para no cambiar)' : '(obligatoria)' ?></label>
        <input type="password" name="password" class="form-control" <?= $id == 0 ? 'required' : '' ?>>
    </div>
    <?php endif; ?>

    <div class="mt-3">
        <label class="form-label fw-bold">Rol</label>
        <select name="rol" class="form-select" <?= $rol_actual === 'supervisor' ? 'disabled' : '' ?>>
            <?php
            $roles_posibles = $rol_actual === 'admin' ? ['admin','dueño','supervisor','empleado'] : 
                              ($rol_actual === 'dueño' ? ['supervisor','empleado'] : ['empleado']);
            foreach ($roles_posibles as $r) {
                $selected = $usuario['rol'] === $r ? 'selected' : '';
                echo "<option value=\"$r\" $selected>" . ucfirst($r) . "</option>";
            }
            ?>
        </select>
        <?php if ($rol_actual === 'supervisor'): ?>
            <input type="hidden" name="rol" value="empleado">
            <div class="form-text text-muted">Solo puedes crear empleados.</div>
        <?php endif; ?>
    </div>

    <div class="mt-3">
        <div class="form-check">
            <input type="checkbox" name="activo" class="form-check-input" id="activo" <?= $usuario['activo'] ? 'checked' : '' ?>>
            <label class="form-check-label" for="activo">Usuario activo</label>
        </div>
    </div>

    <!-- Empresas -->
    <div class="mt-4">
        <label class="form-label fw-bold">Empresas asignadas</label>
        <select name="empresas[]" class="form-select" multiple size="5">
            <?php foreach ($empresas as $e): 
                $selected = in_array($e['id'], $empresas_asignadas) ? 'selected' : '';
            ?>
                <option value="<?= $e['id'] ?>" <?= $selected ?>>
                    <?= htmlspecialchars($e['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div class="form-text">Mantén Ctrl/Cmd para seleccionar varias</div>
    </div>

    <!-- Sucursales -->
    <div class="mt-4">
        <label class="form-label fw-bold">Sucursales asignadas</label>
        <select name="sucursales[]" class="form-select" multiple size="8">
            <?php foreach ($sucursales as $s): 
                $selected = in_array($s['id'], $sucursales_asignadas) ? 'selected' : '';
            ?>
                <option value="<?= $s['id'] ?>" <?= $selected ?>>
                    <?= htmlspecialchars($s['empresa'] . ' → ' . $s['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mt-5 d-flex gap-2 justify-content-end">
        <a href="?action=usuarios" class="btn btn-secondary">Cancelar</a>
        <button type="submit" class="btn btn-success px-5">Guardar Usuario</button>
    </div>
</form>