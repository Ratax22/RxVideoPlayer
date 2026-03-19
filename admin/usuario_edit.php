<?php
// ================================================
// SECCIÓN 1: INICIO Y PROTECCIÓN
// ================================================
session_start();
require_once '../config.php';
require_once 'proteccion.php';

// ================================================
// SECCIÓN 2: CHEQUEO DE PERMISOS (quién puede usar esta página)
// ================================================
$rol_actual = $_SESSION['rol'];
if ($rol_actual === 'empleado') {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Acceso denegado.'];
    header("Location: index.php");
    exit;
}

// ================================================
// SECCIÓN 3: VARIABLES Y CARGA DE DATOS
// ================================================
$errors = [];
$usuario = [
    'id'       => 0,
    'email'    => '',
    'nombre'   => '',
    'rol'      => 'empleado',
    'activo'   => 1,
    'password' => ''  // solo para nuevo
];
$empresas_asignadas = [];
$sucursales_asignadas = [];

// ID del usuario a editar (si es edición)
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Cargar datos si es edición
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT id, email, nombre, rol, activo FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC) ?: $usuario;

    // Empresas asignadas
    $stmt = $pdo->prepare("SELECT empresa_id FROM usuario_empresa WHERE usuario_id = ?");
    $stmt->execute([$id]);
    $empresas_asignadas = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Sucursales asignadas
    $stmt = $pdo->prepare("SELECT sucursal_id FROM usuario_sucursal WHERE usuario_id = ?");
    $stmt->execute([$id]);
    $sucursales_asignadas = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Listado de empresas disponibles (según permisos del usuario actual)
$empresas = [];
if ($rol_actual === 'admin') {
    $stmt = $pdo->query("SELECT id, nombre FROM empresas WHERE activo = 1 ORDER BY nombre");
    $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $empresas_ids = getEmpresasAcceso($pdo, $_SESSION['usuario_id'], $rol_actual);
    if (!empty($empresas_ids)) {
        $placeholders = implode(',', array_fill(0, count($empresas_ids), '?'));
        $stmt = $pdo->prepare("SELECT id, nombre FROM empresas WHERE id IN ($placeholders) AND activo = 1 ORDER BY nombre");
        $stmt->execute($empresas_ids);
        $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Listado de sucursales (filtrado por empresas visibles)
$sucursales = [];
if (!empty($empresas)) {
    $empresas_ids = array_column($empresas, 'id');
    $placeholders = implode(',', array_fill(0, count($empresas_ids), '?'));
    $stmt = $pdo->prepare("
        SELECT s.id, s.nombre, e.nombre AS empresa 
        FROM sucursales s 
        INNER JOIN empresas e ON s.empresa_id = e.id 
        WHERE s.empresa_id IN ($placeholders) AND s.activo = 1 
        ORDER BY e.nombre, s.nombre
    ");
    $stmt->execute($empresas_ids);
    $sucursales = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ================================================
// SECCIÓN 4: PROCESAR FORMULARIO (POST)
// ================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id             = (int)($_POST['id'] ?? 0);
    $email          = trim($_POST['email'] ?? '');
    $nombre         = trim($_POST['nombre'] ?? '');
    $rol            = $_POST['rol'] ?? 'empleado';
    $activo         = isset($_POST['activo']) ? 1 : 0;
    $password       = trim($_POST['password'] ?? '');
    $empresas_post  = $_POST['empresas'] ?? [];
    $sucursales_post = $_POST['sucursales'] ?? [];

    // Validaciones básicas
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email inválido o vacío.";
    }
    if (empty($nombre)) {
        $errors[] = "El nombre es obligatorio.";
    }

    // Chequear unicidad de email
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
    $stmt->execute([$email, $id]);
    if ($stmt->fetch()) {
        $errors[] = "Ese email ya está registrado.";
    }

    // Validar rol según quién edita
    if ($rol_actual === 'supervisor' && $rol !== 'empleado') {
        $errors[] = "Solo puedes crear/editar empleados.";
    }
    if ($rol_actual === 'dueño' && !in_array($rol, ['supervisor', 'empleado'])) {
        $errors[] = "Solo puedes crear supervisores o empleados.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            if ($id > 0) {
                // UPDATE
                $sql = "UPDATE usuarios SET email = ?, nombre = ?, rol = ?, activo = ?";
                $params = [$email, $nombre, $rol, $activo];
                if (!empty($password)) {
                    $sql .= ", password_hash = ?";
                    $params[] = password_hash($password, PASSWORD_DEFAULT);
                }
                $sql .= " WHERE id = ?";
                $params[] = $id;

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            } else {
                // INSERT (nuevo usuario)
                if (empty($password)) {
                    $errors[] = "Contraseña obligatoria para nuevo usuario.";
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO usuarios 
                        (email, password_hash, nombre, rol, activo) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT), $nombre, $rol, $activo]);
                    $id = $pdo->lastInsertId();
                }
            }

            if (empty($errors)) {
                // Asignar empresas
                $pdo->prepare("DELETE FROM usuario_empresa WHERE usuario_id = ?")->execute([$id]);
                foreach ($empresas_post as $emp_id) {
                    $pdo->prepare("INSERT IGNORE INTO usuario_empresa (usuario_id, empresa_id) VALUES (?, ?)")->execute([$id, (int)$emp_id]);
                }

                // Asignar sucursales
                $pdo->prepare("DELETE FROM usuario_sucursal WHERE usuario_id = ?")->execute([$id]);
                foreach ($sucursales_post as $suc_id) {
                    $pdo->prepare("INSERT IGNORE INTO usuario_sucursal (usuario_id, sucursal_id) VALUES (?, ?)")->execute([$id, (int)$suc_id]);
                }

                $pdo->commit();
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Usuario guardado correctamente'];
                header("Location: usuarios.php");
                exit;
            } else {
                $pdo->rollBack();
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Error en BD: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $usuario['id'] ? 'Editar' : 'Nuevo' ?> Usuario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-5" style="max-width: 800px;">

    <h2 class="mb-4"><?= $usuario['id'] ? 'Editar Usuario' : 'Crear Nuevo Usuario' ?></h2>

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
        <input type="hidden" name="id" value="<?= htmlspecialchars($usuario['id']) ?>">

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

        <?php if ($usuario['id'] == 0 || !empty($password)): ?>
        <div class="mt-3">
            <label class="form-label fw-bold">Contraseña <?= $usuario['id'] > 0 ? '(dejar vacío para no cambiar)' : '(obligatoria)' ?></label>
            <input type="password" name="password" class="form-control" <?= $usuario['id'] == 0 ? 'required' : '' ?>>
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

        <div class="mt-5 d-flex gap-3 justify-content-end">
            <a href="usuarios.php" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-success px-5">Guardar Usuario</button>
        </div>
    </form>

</div>

</body>
</html>