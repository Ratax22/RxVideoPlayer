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
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'No tienes permiso para administrar sucursales.'];
    header("Location: index.php");
    exit;
}

// ================================================
// SECCIÓN 3: CARGAR SUCURSALES SEGÚN PERMISOS
// ================================================
$sucursales = [];
$empresas_ids = getEmpresasAcceso($pdo, $_SESSION['usuario_id'], $rol);

if ($rol === 'admin') {
    // Admin ve todas
    $stmt = $pdo->query("
        SELECT s.id, s.nombre, s.activo, s.created_at, e.nombre AS empresa 
        FROM sucursales s 
        INNER JOIN empresas e ON s.empresa_id = e.id 
        ORDER BY e.nombre, s.nombre
    ");
    $sucursales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif (!empty($empresas_ids)) {
    $placeholders = implode(',', array_fill(0, count($empresas_ids), '?'));
    $stmt = $pdo->prepare("
        SELECT s.id, s.nombre, s.activo, s.created_at, e.nombre AS empresa 
        FROM sucursales s 
        INNER JOIN empresas e ON s.empresa_id = e.id 
        WHERE s.empresa_id IN ($placeholders) 
        ORDER BY e.nombre, s.nombre
    ");
    $stmt->execute($empresas_ids);
    $sucursales = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ================================================
// SECCIÓN 4: PROCESAR ACCIONES (activar/desactivar/eliminar)
// ================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $sucursal_id = (int)$_POST['sucursal_id'];
    $action = $_POST['action'];

    // Solo admin puede eliminar sucursales
    $can_delete = ($rol === 'admin');

    try {
        if ($action === 'toggle_activo') {
            $stmt = $pdo->prepare("UPDATE sucursales SET activo = NOT activo WHERE id = ?");
            $stmt->execute([$sucursal_id]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Estado actualizado'];
        } elseif ($action === 'delete' && $can_delete) {
            $pdo->prepare("DELETE FROM sucursales WHERE id = ?")->execute([$sucursal_id]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Sucursal eliminada'];
        }
        header("Location: sucursales.php");
        exit;
    } catch (PDOException $e) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Error: ' . $e->getMessage()];
        header("Location: sucursales.php");
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
    <title>Administración de Sucursales</title>
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
        <h1>Sucursales</h1>
        <a href="sucursal_edit.php" class="btn btn-primary">+ Nueva Sucursal</a>
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-bordered align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Empresa</th>
                    <th>Sucursal</th>
                    <th>Estado</th>
                    <th>Creada</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($sucursales as $s): ?>
                <tr>
                    <td><?= htmlspecialchars($s['empresa']) ?></td>
                    <td><?= htmlspecialchars($s['nombre']) ?></td>
                    <td>
                        <span class="badge bg-<?= $s['activo'] ? 'success' : 'danger' ?>">
                            <?= $s['activo'] ? 'Activa' : 'Inactiva' ?>
                        </span>
                    </td>
                    <td><?= date('d/m/Y', strtotime($s['created_at'])) ?></td>
                    <td>
                        <a href="sucursal_edit.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-warning">Editar</a>
                        <form method="post" style="display:inline;" 
                              onsubmit="return confirm('¿<?= $s['activo'] ? 'Desactivar' : 'Activar' ?> esta sucursal?')">
                            <input type="hidden" name="action" value="toggle_activo">
                            <input type="hidden" name="sucursal_id" value="<?= $s['id'] ?>">
                            <button type="submit" class="btn btn-sm <?= $s['activo'] ? 'btn-outline-secondary' : 'btn-outline-success' ?>">
                                <?= $s['activo'] ? 'Desactivar' : 'Activar' ?>
                            </button>
                        </form>
                        <?php if ($rol === 'admin'): ?>
                            <form method="post" style="display:inline;" 
                                  onsubmit="return confirm('¿Eliminar sucursal permanentemente? (se borrarán relaciones)')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="sucursal_id" value="<?= $s['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($sucursales)): ?>
                <tr><td colspan="5" class="text-center py-4">No hay sucursales para mostrar</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>