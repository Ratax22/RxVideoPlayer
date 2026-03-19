<?php
// ================================================
// SECCIÓN 1: INICIO Y PROTECCIÓN
// ================================================
session_start();
require_once '../config.php';
require_once 'proteccion.php';

// ================================================
// SECCIÓN 2: CHEQUEO DE PERMISOS
// ================================================
$rol = $_SESSION['rol'];
if ($rol !== 'admin' && $rol !== 'dueño') {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'No tienes permiso para editar sucursales.'];
    header("Location: sucursales.php");
    exit;
}

// ================================================
// SECCIÓN 3: VARIABLES INICIALES
// ================================================
$errors = [];
$sucursal = [
    'id'          => 0,
    'empresa_id'  => 0,
    'nombre'      => '',
    'activo'      => 1
];

// ID de la sucursal a editar
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ================================================
// SECCIÓN 4: CARGAR DATOS SI ES EDICIÓN + CHEQUEO PERMISO
// ================================================
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT id, empresa_id, nombre, activo FROM sucursales WHERE id = ?");
    $stmt->execute([$id]);
    $sucursal = $stmt->fetch(PDO::FETCH_ASSOC) ?: $sucursal;

    if (!$sucursal['id']) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Sucursal no encontrada.'];
        header("Location: sucursales.php");
        exit;
    }

    // Chequear permiso sobre la empresa padre
    $empresas_permitidas = getEmpresasAcceso($pdo, $_SESSION['usuario_id'], $rol);
    if ($rol !== 'admin' && !in_array($sucursal['empresa_id'], $empresas_permitidas)) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'No tienes permiso para editar esta sucursal.'];
        header("Location: sucursales.php");
        exit;
    }
}

// ================================================
// SECCIÓN 5: LISTADO DE EMPRESAS QUE PUEDE ASIGNAR
// ================================================
$empresas = [];
$empresas_ids_permitidas = getEmpresasAcceso($pdo, $_SESSION['usuario_id'], $rol);

if ($rol === 'admin') {
    $stmt = $pdo->query("SELECT id, nombre FROM empresas WHERE activo = 1 ORDER BY nombre");
    $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif (!empty($empresas_ids_permitidas)) {
    $placeholders = implode(',', array_fill(0, count($empresas_ids_permitidas), '?'));
    $stmt = $pdo->prepare("SELECT id, nombre FROM empresas WHERE id IN ($placeholders) AND activo = 1 ORDER BY nombre");
    $stmt->execute($empresas_ids_permitidas);
    $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ================================================
// SECCIÓN 6: PROCESAR FORMULARIO (POST)
// ================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id          = (int)($_POST['id'] ?? 0);
    $empresa_id  = (int)($_POST['empresa_id'] ?? 0);
    $nombre      = trim($_POST['nombre'] ?? '');
    $activo      = isset($_POST['activo']) ? 1 : 0;

    // Validaciones
    if (empty($nombre)) {
        $errors[] = "El nombre de la sucursal es obligatorio.";
    }
    if ($empresa_id <= 0) {
        $errors[] = "Debes seleccionar una empresa.";
    }

    // Chequear permiso sobre la empresa seleccionada
    $empresas_permitidas = getEmpresasAcceso($pdo, $_SESSION['usuario_id'], $rol);
    if ($rol !== 'admin' && !in_array($empresa_id, $empresas_permitidas)) {
        $errors[] = "No tienes permiso para asignar esta empresa.";
    }

    // Unicidad de nombre dentro de la empresa (opcional pero útil)
    $stmt = $pdo->prepare("SELECT id FROM sucursales WHERE nombre = ? AND empresa_id = ? AND id != ?");
    $stmt->execute([$nombre, $empresa_id, $id]);
    if ($stmt->fetch()) {
        $errors[] = "Ya existe una sucursal con ese nombre en esta empresa.";
    }

    if (empty($errors)) {
        try {
            if ($id > 0) {
                // UPDATE
                $stmt = $pdo->prepare("
                    UPDATE sucursales SET empresa_id = ?, nombre = ?, activo = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$empresa_id, $nombre, $activo, $id]);
            } else {
                // INSERT
                $stmt = $pdo->prepare("
                    INSERT INTO sucursales (empresa_id, nombre, activo) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$empresa_id, $nombre, $activo]);
            }

            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Sucursal guardada correctamente'];
            header("Location: sucursales.php");
            exit;
        } catch (PDOException $e) {
            $errors[] = "Error en base de datos: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $id ? 'Editar' : 'Nueva' ?> Sucursal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-5" style="max-width: 600px;">

    <h2 class="mb-4"><?= $id ? 'Editar Sucursal' : 'Crear Nueva Sucursal' ?></h2>

    <?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $err): ?>
                <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="post">
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

        <div class="mb-4 form-check">
            <input type="checkbox" name="activo" class="form-check-input" id="activo" <?= $sucursal['activo'] ? 'checked' : '' ?>>
            <label class="form-check-label" for="activo">Sucursal activa</label>
        </div>

        <div class="d-flex gap-2 justify-content-end">
            <a href="sucursales.php" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-success px-4">Guardar Sucursal</button>
        </div>
    </form>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>