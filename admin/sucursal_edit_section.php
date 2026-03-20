<?php
// ================================================
// sucursal_edit_section.php
// ================================================

$errors = [];
$sucursal = [
    'id'          => 0,
    'empresa_id'  => 0,
    'nombre'      => '',
    'direccion'   => '',
    'activo'      => 1
];

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT id, empresa_id, nombre, direccion, activo FROM sucursales WHERE id = ?");
    $stmt->execute([$id]);
    $sucursal = $stmt->fetch(PDO::FETCH_ASSOC) ?: $sucursal;

    if (!$sucursal['id']) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Sucursal no encontrada.'];
        header("Location: ?action=sucursales");
        exit;
    }

    $empresas_permitidas = getEmpresasAcceso($pdo, $_SESSION['usuario_id'], $_SESSION['rol']);
    if ($_SESSION['rol'] !== 'admin' && !in_array($sucursal['empresa_id'], $empresas_permitidas)) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'No tienes permiso para editar esta sucursal.'];
        header("Location: ?action=sucursales");
        exit;
    }
}

$empresas = [];
$empresas_ids_permitidas = getEmpresasAcceso($pdo, $_SESSION['usuario_id'], $_SESSION['rol']);

if ($_SESSION['rol'] === 'admin') {
    $stmt = $pdo->query("SELECT id, nombre FROM empresas WHERE activo = 1 ORDER BY nombre");
    $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif (!empty($empresas_ids_permitidas)) {
    $placeholders = implode(',', array_fill(0, count($empresas_ids_permitidas), '?'));
    $stmt = $pdo->prepare("SELECT id, nombre FROM empresas WHERE id IN ($placeholders) AND activo = 1 ORDER BY nombre");
    $stmt->execute($empresas_ids_permitidas);
    $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<h2 class="mb-4"><?= $id ? 'Editar Sucursal' : 'Crear Nueva Sucursal' ?></h2>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <ul class="mb-0">
        <?php foreach ($errors as $err): ?>
            <li><?= htmlspecialchars($err) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<form method="post" action="?action=sucursales">
    <input type="hidden" name="id" value="<?= $id ?>">

    <div class="mb-3">
        <label class="form-label fw-bold">Empresa</label>
        <select name="empresa_id" class="form-select" required>
            <option value="">Seleccionar empresa...</option>
            <?php foreach ($empresas as $e): ?>
                <option value="<?= $e['id'] ?>" <?= $sucursal['empresa_id'] == $e['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($e['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mb-3">
        <label class="form-label fw-bold">Nombre de la sucursal</label>
        <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($sucursal['nombre']) ?>" required>
    </div>

    <div class="mb-3">
        <label class="form-label fw-bold">Dirección (opcional)</label>
        <input type="text" name="direccion" class="form-control" 
               value="<?= htmlspecialchars($sucursal['direccion'] ?? '') ?>" 
               placeholder="Ej: Av. Córdoba 1234, CABA" maxlength="255">
    </div>

    <div class="mb-4 form-check">
        <input type="checkbox" name="activo" class="form-check-input" id="activo" <?= $sucursal['activo'] ? 'checked' : '' ?>>
        <label class="form-check-label" for="activo">Sucursal activa</label>
    </div>

    <div class="d-flex gap-2 justify-content-end">
        <a href="?action=sucursales" class="btn btn-secondary">Cancelar</a>
        <button type="submit" class="btn btn-success px-4">Guardar Sucursal</button>
    </div>
</form>