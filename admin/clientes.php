<?php
require_once 'proteccion.php';
require_once '../config.php';

// Filtrar clientes según rol y permisos
$sucursales_ids = getSucursalesAcceso($pdo, $_SESSION['usuario_id'], $_SESSION['rol']);

if ($_SESSION['rol'] === 'dueño') {
    // Dueño ve todo (o solo de sus empresas - según necesites)
    $clientes = $pdo->query("SELECT * FROM clients ORDER BY name ASC")->fetchAll();
} else {
    // Supervisor y empleado solo ven clientes de sus sucursales
    if (empty($sucursales_ids)) {
        $clientes = [];
    } else {
        $placeholders = implode(',', array_fill(0, count($sucursales_ids), '?'));
        $stmt = $pdo->prepare("
            SELECT c.* 
            FROM clients c
            INNER JOIN client_sucursal cs ON c.id = cs.client_id   -- asumiendo que agregarás esta tabla
            WHERE cs.sucursal_id IN ($placeholders)
            ORDER BY c.name ASC
        ");
        $stmt->execute($sucursales_ids);
        $clientes = $stmt->fetchAll();
    }
}

// Procesar eliminación
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $pdo->prepare("DELETE FROM clients WHERE id = ?")->execute([$id]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Cliente eliminado correctamente'];
    } catch (Exception $e) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Error al eliminar: ' . $e->getMessage()];
    }
    header("Location: clients.php");
    exit;
}

// Función para estado del cliente
function getClientStatus($last_ping) {
    if (!$last_ping) return ['text' => 'Nunca', 'badge' => 'secondary'];
    $diff = time() - strtotime($last_ping);
    if ($diff < 300)       return ['text' => 'Activo',      'badge' => 'success'];
    if ($diff < 900)       return ['text' => 'Inactivo',    'badge' => 'warning'];
    return ['text' => 'Desconectado', 'badge' => 'danger'];
}

// Flash message
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Listado
$stmt = $pdo->query("SELECT * FROM clients ORDER BY name ASC");
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
<div class="collapse navbar-collapse">
<ul class="navbar-nav ms-auto">
<li class="nav-item"><a class="nav-link" href="logout.php">Salir</a></li>
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
<a href="cliente_edit.php" class="btn btn-primary">+ Nuevo cliente</a>
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
<?php foreach ($clients as $row):
$status = getClientStatus($row['last_ping']);
?>
<tr>
<td><?= htmlspecialchars($row['name'] ?: '—') ?></td>
<td><code><?= htmlspecialchars($row['client_key']) ?></code></td>
<td><?= htmlspecialchars($row['orientation']) ?></td>
<td><?= htmlspecialchars($row['background'] ?: '—') ?></td>
<td><?= $row['last_ping'] ?: '—' ?></td>
<td><span class="badge bg-<?= $status['badge'] ?>"><?= $status['text'] ?></span></td>
<td><?= $row['playlist_version'] ?? 0 ?></td>
<td>
<a href="cliente_edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-warning">Editar</a>
<a href="assign.php?client_id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary">Playlist</a>
<a href="?force_refresh=<?= $row['id'] ?>"
onclick="return confirm('Forzar actualización completa?')"
class="btn btn-sm btn-outline-info">Refresh</a>
<a href="?delete=<?= $row['id'] ?>" 
   onclick="return confirm('¿Eliminar cliente? También se quitarán sus asignaciones de videos.')"
   class="btn btn-sm btn-outline-danger">Eliminar</a>
</td>
</tr>
<?php endforeach; ?>
<?php if (empty($clients)): ?>
<tr><td colspan="8" class="text-center py-4">No hay clientes aún</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
