<?php
// ================================================
// clientes_section.php
// Contenido que se incluye cuando action=clientes
// ================================================

// Ya tenemos $pdo, sesión y rol disponibles (heredados de index.php)

// Sucursales accesibles (ya definido en proteccion.php)
$sucursales_ids = getSucursalesAcceso($pdo, $_SESSION['usuario_id'], $_SESSION['rol']);

// Cargar clientes según permisos
$clientes = [];
if ($_SESSION['rol'] === 'admin') {
    $stmt = $pdo->query("SELECT * FROM clients ORDER BY name ASC");
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
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

// Función estado (ya la tenías)
function getClientStatus($last_ping) {
    if (!$last_ping) return ['text' => 'Nunca', 'badge' => 'secondary'];
    $diff = time() - strtotime($last_ping);
    if ($diff < 300) return ['text' => 'Activo', 'badge' => 'success'];
    if ($diff < 900) return ['text' => 'Inactivo', 'badge' => 'warning'];
    return ['text' => 'Desconectado', 'badge' => 'danger'];
}

// Flash message (heredado de index.php)
if (isset($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
}
?>

<h1 class="mb-4">Dispositivos / Clientes</h1>

<?php if (isset($flash)): ?>
<div class="alert alert-<?= $flash['type'] ?? 'info' ?> alert-dismissible fade show">
    <?= htmlspecialchars($flash['message'] ?? '') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($_SESSION['rol'] !== 'empleado'): ?>
    <a href="?action=cliente_nuevo" class="btn btn-primary mb-3">+ Nuevo dispositivo</a>
<?php endif; ?>

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
                        <a href="?action=cliente_edit&id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-warning">Editar</a>
                    <?php endif; ?>
                    <a href="?action=assign_playlist&client_id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary">Playlist</a>
                    <?php if ($_SESSION['rol'] === 'admin' || $_SESSION['rol'] === 'dueño'): ?>
                        <a href="?action=cliente_delete&id=<?= $row['id'] ?>" 
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