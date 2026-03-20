<?php
// ================================================
// empresa_edit_section.php
// ================================================

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$errors = [];
$empresa = [
    'id'       => 0,
    'nombre'   => '',
    'cuit'     => '',
    'activo'   => 1,
    'creado_por' => $_SESSION['usuario_id']
];

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT id, nombre, cuit, activo, creado_por FROM empresas WHERE id = ?");
    $stmt->execute([$id]);
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC) ?: $empresa;

    if (!$empresa['id']) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Empresa no encontrada.'];
        header("Location: ?action=empresas");
        exit;
    }

    $empresas_permitidas = getEmpresasAcceso($pdo, $_SESSION['usuario_id'], $_SESSION['rol']);
    if ($_SESSION['rol'] !== 'admin' && !in_array($id, $empresas_permitidas)) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'No tienes permiso para editar esta empresa.'];
        header("Location: ?action=empresas");
        exit;
    }
}

$posibles_duenos = [];
if ($id === 0 && $_SESSION['rol'] === 'admin') {
    $stmt = $pdo->query("SELECT id, nombre, email FROM usuarios WHERE rol = 'dueño' AND activo = 1 ORDER BY nombre");
    $posibles_duenos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<h2 class="mb-4"><?= $id ? 'Editar Empresa' : 'Crear Nueva Empresa' ?></h2>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <ul class="mb-0">
        <?php foreach ($errors as $err): ?>
            <li><?= htmlspecialchars($err) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<form method="post" action="?action=empresas">
    <input type="hidden" name="id" value="<?= $id ?>">

    <div class="mb-3">
        <label class="form-label fw-bold">Nombre de la empresa</label>
        <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($empresa['nombre']) ?>" required autofocus>
    </div>

    <div class="mb-3">
        <label class="form-label fw-bold">CUIT (opcional)</label>
        <input type="text" name="cuit" class="form-control" value="<?= htmlspecialchars($empresa['cuit'] ?? '') ?>" placeholder="Ej: 30-12345678-9" maxlength="20">
    </div>

    <?php if ($id === 0 && $_SESSION['rol'] === 'admin'): ?>
    <div class="mb-3">
        <label class="form-label fw-bold">Dueño inicial</label>
        <select name="creado_por" class="form-select" required>
            <?php foreach ($posibles_duenos as $dueno): ?>
                <option value="<?= $dueno['id'] ?>" <?= $dueno['id'] == $empresa['creado_por'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($dueno['nombre'] . ' (' . $dueno['email'] . ')') ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php else: ?>
        <input type="hidden" name="creado_por" value="<?= $_SESSION['usuario_id'] ?>">
    <?php endif; ?>

    <div class="mb-4 form-check">
        <input type="checkbox" name="activo" class="form-check-input" id="activo" <?= $empresa['activo'] ? 'checked' : '' ?>>
        <label class="form-check-label" for="activo">Empresa activa</label>
    </div>

    <div class="d-flex gap-2 justify-content-end">
        <a href="?action=empresas" class="btn btn-secondary">Cancelar</a>
        <button type="submit" class="btn btn-success px-4">Guardar Empresa</button>
    </div>
</form>