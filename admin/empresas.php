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
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'No tienes permiso para administrar empresas.'];
    header("Location: index.php");
    exit;
}

// ================================================
// SECCIÓN 3: CARGAR EMPRESAS SEGÚN PERMISOS
// ================================================
$empresas = [];
$empresas_ids = getEmpresasAcceso($pdo, $_SESSION['usuario_id'], $rol);

if ($rol === 'admin') {
    // Admin ve todas
    $stmt = $pdo->query("SELECT id, nombre, activo, created_at FROM empresas ORDER BY nombre");
    $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif (!empty($empresas_ids)) {
    $placeholders = implode(',', array_fill(0, count($empresas_ids), '?'));
    $stmt = $pdo->prepare("
        SELECT id, nombre, activo, created_at 
        FROM empresas 
        WHERE id IN ($placeholders) 
        ORDER BY nombre
    ");
    $stmt->execute($empresas_ids);
    $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ================================================
// SECCIÓN 4: PROCESAR ACCIONES (deshabilitar/habilitar/eliminar)
// ================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $empresa_id = (int)$_POST['empresa_id'];
    $action = $_POST['action'];

    // Solo admin puede eliminar empresas
    $can_delete = ($rol === 'admin');

    try {
        if ($action === 'toggle_activo') {
            $stmt = $pdo->prepare("UPDATE empresas SET activo = NOT activo WHERE id = ?");
            $stmt->execute([$empresa_id]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Estado de la empresa actualizado'];
        } elseif ($action === 'delete' && $can_delete) {
            // Borrar empresa + cascada (sucursales, relaciones, etc.)
            $pdo->prepare("DELETE FROM empresas WHERE id = ?")->execute([$empresa_id]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Empresa eliminada permanentemente'];
        }
        header("Location: empresas.php");
        exit;
    } catch (PDOException $e) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Error: ' . $e->getMessage()];
        header("Location: empresas.php");
        exit;
    }
}

// Flash
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administración de Empresas</title>
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
        <h1>Empresas</h1>
        <a href="empresa_edit.php" class="btn btn-primary">+ Nueva Empresa</a>
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-bordered align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Nombre</th>
                    <th>Estado</th>
                    <th>Creada</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($empresas as $e): ?>
                <tr>
                    <td><?= htmlspecialchars($e['nombre']) ?></td>
                    <td>
                        <span class="badge bg-<?= $e['activo'] ? 'success' : 'danger' ?>">
                            <?= $e['activo'] ? 'Activa' : 'Inactiva' ?>
                        </span>
                    </td>
                    <td><?= date('d/m/Y', strtotime($e['created_at'])) ?></td>
                    <td>
                        <a href="empresa_edit.php?id=<?= $e['id'] ?>" class="btn btn-sm btn-outline-warning">Editar</a>
                        <form method="post" style="display:inline;" 
                              onsubmit="return confirm('¿<?= $e['activo'] ? 'Desactivar' : 'Activar' ?> esta empresa?')">
                            <input type="hidden" name="action" value="toggle_activo">
                            <input type="hidden" name="empresa_id" value="<?= $e['id'] ?>">
                            <button type="submit" class="btn btn-sm <?= $e['activo'] ? 'btn-outline-secondary' : 'btn-outline-success' ?>">
                                <?= $e['activo'] ? 'Desactivar' : 'Activar' ?>
                            </button>
                        </form>
                        <?php if ($rol === 'admin'): ?>
                            <form method="post" style="display:inline;" 
                                  onsubmit="return confirm('¿Eliminar empresa permanentemente? (se borrarán sucursales y relaciones)')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="empresa_id" value="<?= $e['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($empresas)): ?>
                <tr><td colspan="4" class="text-center py-4">No hay empresas para mostrar</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>