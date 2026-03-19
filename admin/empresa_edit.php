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
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'No tienes permiso para editar empresas.'];
    header("Location: empresas.php");
    exit;
}

// ================================================
// SECCIÓN 3: VARIABLES INICIALES
// ================================================
$errors = [];
$empresa = [
    'id'       => 0,
    'nombre'   => '',
    'activo'   => 1,
    'creado_por' => $_SESSION['usuario_id']  // por defecto el usuario actual
];

// ID de la empresa a editar
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ================================================
// SECCIÓN 4: CARGAR DATOS SI ES EDICIÓN + CHEQUEO PERMISO
// ================================================
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT id, nombre, activo, creado_por FROM empresas WHERE id = ?");
    $stmt->execute([$id]);
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC) ?: $empresa;

    if (!$empresa['id']) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Empresa no encontrada.'];
        header("Location: empresas.php");
        exit;
    }

    // Chequear permiso: admin o dueño de esta empresa
    $empresas_permitidas = getEmpresasAcceso($pdo, $_SESSION['usuario_id'], $rol);
    if ($rol !== 'admin' && !in_array($id, $empresas_permitidas)) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'No tienes permiso para editar esta empresa.'];
        header("Location: empresas.php");
        exit;
    }
}

// ================================================
// SECCIÓN 5: LISTADO DE USUARIOS QUE PUEDEN SER DUEÑOS (para nuevo)
// ================================================
$posibles_duenos = [];
if ($id === 0) {  // solo al crear nuevo
    if ($rol === 'admin') {
        $stmt = $pdo->query("SELECT id, nombre, email FROM usuarios WHERE rol = 'dueño' AND activo = 1 ORDER BY nombre");
        $posibles_duenos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Dueño solo puede crear empresas para sí mismo
        $posibles_duenos = [['id' => $_SESSION['usuario_id'], 'nombre' => $_SESSION['nombre'], 'email' => $_SESSION['email']]];
    }
}

// ================================================
// SECCIÓN 6: PROCESAR FORMULARIO (POST)
// ================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = (int)($_POST['id'] ?? 0);
    $nombre   = trim($_POST['nombre'] ?? '');
    $activo   = isset($_POST['activo']) ? 1 : 0;
    $creado_por = (int)($_POST['creado_por'] ?? $_SESSION['usuario_id']);

    // Validaciones
    if (empty($nombre)) {
        $errors[] = "El nombre de la empresa es obligatorio.";
    }

    // Unicidad de nombre (opcional, pero útil)
    $stmt = $pdo->prepare("SELECT id FROM empresas WHERE nombre = ? AND id != ?");
    $stmt->execute([$nombre, $id]);
    if ($stmt->fetch()) {
        $errors[] = "Ya existe una empresa con ese nombre.";
    }

    // Validar que el dueño seleccionado sea válido según rol
    if ($id === 0 && $rol !== 'admin') {
        $creado_por = $_SESSION['usuario_id'];  // Dueño solo puede crearse a sí mismo
    }

    if (empty($errors)) {
        try {
            if ($id > 0) {
                // UPDATE
                $stmt = $pdo->prepare("
                    UPDATE empresas SET nombre = ?, activo = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$nombre, $activo, $id]);
            } else {
                // INSERT
                $stmt = $pdo->prepare("
                    INSERT INTO empresas (nombre, activo, creado_por) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$nombre, $activo, $creado_por]);

                $id = $pdo->lastInsertId();

                // Asignar automáticamente al dueño creador
                $pdo->prepare("
                    INSERT IGNORE INTO usuario_empresa (usuario_id, empresa_id) 
                    VALUES (?, ?)
                ")->execute([$creado_por, $id]);
            }

            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Empresa guardada correctamente'];
            header("Location: empresas.php");
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
    <title><?= $id ? 'Editar' : 'Nueva' ?> Empresa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-5" style="max-width: 600px;">

    <h2 class="mb-4"><?= $id ? 'Editar Empresa' : 'Crear Nueva Empresa' ?></h2>

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
            <label class="form-label fw-bold">Nombre de la empresa</label>
            <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($empresa['nombre']) ?>" required autofocus>
        </div>

        <?php if ($id === 0 && $rol === 'admin'): ?>
        <div class="mb-3">
            <label class="form-label fw-bold">Dueño inicial</label>
            <select name="creado_por" class="form-select" required>
                <?php foreach ($posibles_duenos as $dueno): ?>
                    <option value="<?= $dueno['id'] ?>" <?= $dueno['id'] == $empresa['creado_por'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($dueno['nombre'] . ' (' . $dueno['email'] . ')') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">El dueño podrá administrar esta empresa y crear sucursales.</div>
        </div>
        <?php else: ?>
            <input type="hidden" name="creado_por" value="<?= $_SESSION['usuario_id'] ?>">
        <?php endif; ?>

        <div class="mb-4 form-check">
            <input type="checkbox" name="activo" class="form-check-input" id="activo" <?= $empresa['activo'] ? 'checked' : '' ?>>
            <label class="form-check-label" for="activo">Empresa activa</label>
        </div>

        <div class="d-flex gap-2 justify-content-end">
            <a href="empresas.php" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-success px-4">Guardar Empresa</button>
        </div>
    </form>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>