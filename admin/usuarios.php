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
if ($rol === 'empleado') {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'No tienes permiso para acceder a esta sección.'];
    header("Location: index.php");
    exit;
}

// ================================================
// SECCIÓN 3: OBTENER USUARIOS SEGÚN ROL
// ================================================
$usuarios = [];
if ($rol === 'admin') {
    // Admin ve todo
    $stmt = $pdo->query("
        SELECT u.id, u.email, u.nombre, u.rol, u.activo, 
               GROUP_CONCAT(DISTINCT e.nombre SEPARATOR ', ') AS empresas
        FROM usuarios u
        LEFT JOIN usuario_empresa ue ON u.id = ue.usuario_id
        LEFT JOIN empresas e ON ue.empresa_id = e.id
        GROUP BY u.id
        ORDER BY u.rol, u.nombre
    ");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Dueño / Supervisor: solo usuarios de sus empresas/sucursales
    $empresas_ids = getEmpresasAcceso($pdo, $_SESSION['usuario_id'], $rol);
    if (empty($empresas_ids)) {
        $usuarios = [];
    } else {
        $placeholders = implode(',', array_fill(0, count($empresas_ids), '?'));
        $stmt = $pdo->prepare("
            SELECT u.id, u.email, u.nombre, u.rol, u.activo,
                   GROUP_CONCAT(DISTINCT e.nombre SEPARATOR ', ') AS empresas
            FROM usuarios u
            INNER JOIN usuario_empresa ue ON u.id = ue.usuario_id
            INNER JOIN empresas e ON ue.empresa_id = e.id
            WHERE ue.empresa_id IN ($placeholders)
            GROUP BY u.id
            ORDER BY u.rol, u.nombre
        ");
        $stmt->execute($empresas_ids);
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// ================================================
// SECCIÓN 4: PROCESAR ACCIONES (deshabilitar, habilitar, eliminar)
// ================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $target_id = (int)$_POST['target_id'];
    $action    = $_POST['action'];

    // Solo admin o dueño pueden eliminar
    $can_delete = ($rol === 'admin' || $rol === 'dueño');

    try {
        if ($action === 'toggle_activo') {
            $stmt = $pdo->prepare("UPDATE usuarios SET activo = NOT activo WHERE id = ?");
            $stmt->execute([$target_id]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Estado actualizado'];
        } elseif ($action === 'delete' && $can_delete) {
            $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$target_id]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Usuario eliminado'];
        }
        header("Location: usuarios.php");
        exit;
    } catch (PDOException $e) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Error: ' . $e->getMessage()];
        header("Location: usuarios.php");
        exit;
    }
}

// Flash message
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administración de Usuarios</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">Panel</a>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="logout.php">Salir</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container py-4">

    <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
        <?= htmlspecialchars($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Usuarios del Sistema</h1>
        <?php if ($rol === 'admin' || $rol === 'dueño'): ?>
            <a href="usuario_edit.php" class="btn btn-primary">+ Nuevo Usuario</a>
        <?php endif; ?>
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-bordered align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Rol</th>
                    <th>Empresas</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td><?= htmlspecialchars($u['nombre']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><span class="badge bg-<?= $u['rol']==='admin'?'danger':($u['rol']==='dueño'?'warning':'primary') ?>">
                        <?= ucfirst($u['rol']) ?>
                    </span></td>
                    <td><?= htmlspecialchars($u['empresas'] ?: '—') ?></td>
                    <td>
                        <span class="badge bg-<?= $u['activo'] ? 'success' : 'danger' ?>">
                            <?= $u['activo'] ? 'Activo' : 'Inactivo' ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($rol === 'admin' || $rol === 'dueño' || ($rol === 'supervisor' && $u['rol'] === 'empleado')): ?>
                            <a href="usuario_edit.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-warning">Editar</a>
                            <form method="post" style="display:inline;" 
                                  onsubmit="return confirm('¿<?= $u['activo'] ? 'Deshabilitar' : 'Habilitar' ?> este usuario?')">
                                <input type="hidden" name="action" value="toggle_activo">
                                <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn btn-sm <?= $u['activo'] ? 'btn-outline-secondary' : 'btn-outline-success' ?>">
                                    <?= $u['activo'] ? 'Deshabilitar' : 'Habilitar' ?>
                                </button>
                            </form>
                            <?php if ($rol === 'admin' || $rol === 'dueño'): ?>
                                <form method="post" style="display:inline;" 
                                      onsubmit="return confirm('¿Eliminar usuario permanentemente?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($usuarios)): ?>
                <tr><td colspan="6" class="text-center py-4">No hay usuarios para mostrar</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>