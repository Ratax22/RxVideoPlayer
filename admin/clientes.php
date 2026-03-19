<?php
// ================================================
// SECCIÓN 1: INICIO Y PROTECCIÓN
// ================================================
session_start();
require_once '../config.php';
require_once 'proteccion.php';

// ================================================
// SECCIÓN 2: OBTENER SUCURSALES ACCESIBLES
// ================================================
$sucursales_ids = getSucursalesAcceso($pdo, $_SESSION['usuario_id'], $_SESSION['rol']);

// ================================================
// SECCIÓN 3: CARGAR CLIENTES SEGÚN PERMISOS
// ================================================
$clientes = [];
if ($_SESSION['rol'] === 'admin') {
    // Admin ve TODOS los clientes
    $stmt = $pdo->query("SELECT * FROM clients ORDER BY name ASC");
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Dueño / Supervisor / Empleado: solo clientes de sus sucursales
    if (empty($sucursales_ids)) {
        $clientes = [];
    } else {
        $placeholders = implode(',', array_fill(0, count($sucursales_ids), '?'));
        $stmt = $pdo->prepare("
            SELECT DISTINCT c.* 
            FROM clients c
            INNER JOIN client_sucursal cs ON c.id = cs.client_id
            WHERE cs.sucursal_id IN ($placeholders)
            ORDER BY c.name ASC
        ");
        $stmt->execute($sucursales_ids);
        $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// ================================================
// SECCIÓN 4: FUNCIÓN ESTADO (ya la tenías)
// ================================================
function getClientStatus($last_ping) {
    if (!$last_ping) return ['text' => 'Nunca', 'badge' => 'secondary'];
    $diff = time() - strtotime($last_ping);
    if ($diff < 300)       return ['text' => 'Activo',      'badge' => 'success'];
    if ($diff < 900)       return ['text' => 'Inactivo',    'badge' => 'warning'];
    return ['text' => 'Desconectado', 'badge' => 'danger'];
}

// ================================================
// SECCIÓN 5: FLASH MESSAGE
// ================================================
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes - Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">Panel Publicidad</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="logout.php">Salir (<?= htmlspecialchars($_SESSION['nombre'] ?? 'Usuario') ?>)</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container py-4">

    <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?? 'info' ?> alert-dismissible fade show">
        <?= htmlspecialchars($flash['message'] ?? '') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Clientes</h1>
        <?php if ($_SESSION['rol'] !== 'empleado'): ?>
            <a href="cliente_edit.php" class="btn btn-primary">+ Nuevo cliente</a>
        <?php endif; ?>
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-bordered align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Nombre</th>
                    <th>Client Key</th>
                    <th>Orientación</th>
                    <th>Background</th>
                    <th>Último ping</th>
                    <th>Estado</th>
                    <th>Playlist ver.</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($clientes as $row): 
                $status = getClientStatus($row['last_ping']);
            ?>
                <tr>
                    <td><?= htmlspecialchars($row['name'] ?? '—') ?></td>
                    <td><code><?= htmlspecialchars($row['client_key']) ?></code></td>
                    <td><?= htmlspecialchars($row['orientation']) ?></td>
                    <td><?= htmlspecialchars($row['background'] ?: '—') ?></td>
                    <td><?= $row['last_ping'] ?: '—' ?></td>
                    <td><span class="badge bg-<?= $status['badge'] ?>"><?= $status['text'] ?></span></td>
                    <td><?= $row['playlist_version'] ?? 0 ?></td>
                    <td>
                        <?php if ($_SESSION['rol'] !== 'empleado'): ?>
                            <a href="cliente_edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-warning">Editar</a>
                        <?php endif; ?>
                        <a href="assign.php?client_id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary">Playlist</a>
                        <?php if ($_SESSION['rol'] === 'admin' || $_SESSION['rol'] === 'dueño'): ?>
                            <a href="?delete=<?= $row['id'] ?>" 
                               onclick="return confirm('¿Eliminar cliente?')"
                               class="btn btn-sm btn-outline-danger">Eliminar</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($clientes)): ?>
                <tr><td colspan="8" class="text-center py-4">No hay clientes asignados a tus sucursales</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>