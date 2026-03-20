<?php
// ================================================
// usuario_edit_section.php
// Crear nuevo o editar usuario existente
// ================================================

$errors = [];
$usuario = [
    'id'       => 0,
    'email'    => '',
    'nombre'   => '',
    'rol'      => 'empleado',
    'activo'   => 1,
    'password' => ''
];

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    $stmt = $pdo->prepare("
        SELECT id, email, nombre, rol, activo 
        FROM usuarios 
        WHERE id = ?
    ");
    $stmt->execute([$id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC) ?: $usuario;

    if (!$usuario['id']) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Usuario no encontrado.'];
        header("Location: ?action=usuarios");
        exit;
    }

    // Chequear permiso para editar este usuario
    $empresas_permitidas = getEmpresasAcceso($pdo, $_SESSION['usuario_id'], $_SESSION['rol']);
    $stmt = $pdo->prepare("SELECT empresa_id FROM usuario_empresa WHERE usuario_id = ?");
    $stmt->execute([$id]);
    $empresas_usuario = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $puede_editar = $_SESSION['rol'] === 'admin' ||
                    ($_SESSION['rol'] === 'dueño' && array_intersect($empresas_permitidas, $empresas_usuario)) ||
                    ($_SESSION['rol'] === 'supervisor' && $usuario['rol'] === 'empleado');

    if (!$puede_editar) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'No tienes permiso para editar este usuario.'];
        header("Location: ?action=usuarios");
        exit;
    }

    // Cargar empresas y sucursales asignadas
    $stmt = $pdo->prepare("SELECT empresa_id FROM usuario_empresa WHERE usuario_id = ?");
    $stmt->execute([$id]);
    $empresas_asignadas = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $pdo->prepare("SELECT sucursal_id FROM usuario_sucursal WHERE usuario_id = ?");
    $stmt->execute([$id]);
    $sucursales_asignadas = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Empresas y sucursales que el usuario actual puede asignar
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

$sucursales = [];
$sucursales_ids_permitidas = getSucursalesAcceso($pdo, $_SESSION['usuario_id'], $_SESSION['rol']);

if ($_SESSION['rol'] === 'admin') {
    $sucursales = $pdo->query("
        SELECT s.id, s.nombre, e.nombre AS empresa 
        FROM sucursales s 
        INNER JOIN empresas e ON s.empresa_id = e.id 
        WHERE s.activo = 1 
        ORDER BY e.nombre, s.nombre
    ")->fetchAll(PDO::FETCH_ASSOC);
} elseif (!empty($sucursales_ids_permitidas)) {
    $placeholders = implode(',', array_fill(0, count($sucursales_ids_permitidas), '?'));
    $stmt = $pdo->prepare("
        SELECT s.id, s.nombre, e.nombre AS empresa 
        FROM sucursales s 
        INNER JOIN empresas e ON s.empresa_id = e.id 
        WHERE s.id IN ($placeholders) AND s.activo = 1 
        ORDER BY e.nombre, s.nombre
    ");
    $stmt->execute($sucursales_ids_permitidas);
    $sucursales = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ================================================
// PROCESAR FORMULARIO
// ================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id          = (int)($_POST['id'] ?? 0);
    $email       = trim($_POST['email'] ?? '');
    $nombre      = trim($_POST['nombre'] ?? '');
    $rol         = $_POST['rol'] ?? 'empleado';
    $activo      = isset($_POST['activo']) ? 1 : 0;
    $password    = trim($_POST['password'] ?? '');
    $empresas_post = $_POST['empresas'] ?? [];
    $sucursales_post = $_POST['sucursales'] ?? [];

    // Validaciones
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email inválido.";
    }
    if (empty($nombre)) {
        $errors[] = "Nombre obligatorio.";
    }

    // Unicidad email
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
    $stmt->execute([$email, $id]);
    if ($stmt->fetch()) {
        $errors[] = "Ese email ya está registrado.";
    }

    // Restricciones de rol según quién edita
    if ($_SESSION['rol'] === 'supervisor' && $rol !== 'empleado') {
        $errors[] = "Solo puedes crear/editar empleados.";
    }
    if ($_SESSION['rol'] === 'dueño' && !in_array($rol, ['supervisor','empleado'])) {
        $errors[] = "Solo puedes crear supervisores o empleados.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            if ($id > 0) {
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
                // Empresas
                $pdo->prepare("DELETE FROM usuario_empresa WHERE usuario_id = ?")->execute([$id]);
                foreach ($empresas_post as $emp_id) {
                    $pdo->prepare("INSERT IGNORE INTO usuario_empresa (usuario_id, empresa_id) VALUES (?, ?)")->execute([$id, (int)$emp_id]);
                }

                // Sucursales
                $pdo->prepare("DELETE FROM usuario_sucursal WHERE usuario_id = ?")->execute([$id]);
                foreach ($sucursales_post as $suc_id) {
                    $pdo->prepare("INSERT IGNORE INTO usuario_sucursal (usuario_id, sucursal_id) VALUES (?, ?)")->execute([$id, (int)$suc_id]);
                }

                $pdo->commit();
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'Usuario guardado correctamente'];
                header("Location: ?action=usuarios");
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
        <select name="rol" class="form-select" <?= $rol === 'supervisor' ? 'disabled' : '' ?>>
            <?php
            $roles_posibles = $rol === 'admin' ? ['admin','dueño','supervisor','empleado'] : 
                              ($rol === 'dueño' ? ['supervisor','empleado'] : ['empleado']);
            foreach ($roles_posibles as $r) {
                $selected = $usuario['rol'] === $r ? 'selected' : '';
                echo "<option value=\"$r\" $selected>" . ucfirst($r) . "</option>";
            }
            ?>
        </select>
        <?php if ($rol === 'supervisor'): ?>
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