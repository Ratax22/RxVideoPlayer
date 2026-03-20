<h1 class="mb-4">Sucursales</h1>

<?php if (isset($_SESSION['flash'])): ?>
<div class="alert alert-<?= $_SESSION['flash']['type'] ?? 'info' ?> alert-dismissible fade show">
    <?= htmlspecialchars($_SESSION['flash']['message'] ?? '') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['flash']); endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Listado de sucursales</h5>
    <a href="?action=sucursal_nueva" class="btn btn-primary btn-sm">+ Nueva Sucursal</a>
</div>

<div class="table-responsive">
    <table class="table table-hover table-bordered align-middle">
        <thead class="table-dark">
            <tr>
                <th>Empresa</th>
                <th>Sucursal</th>
                <th>Dirección</th>
                <th>Estado</th>
                <th>Creada</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $sucursales = [];
        $empresas_ids = getEmpresasAcceso($pdo, $_SESSION['usuario_id'], $_SESSION['rol']);

        if ($_SESSION['rol'] === 'admin') {
            $stmt = $pdo->query("
                SELECT s.id, s.nombre, s.direccion, s.activo, s.created_at, e.nombre AS empresa 
                FROM sucursales s 
                INNER JOIN empresas e ON s.empresa_id = e.id 
                ORDER BY e.nombre, s.nombre
            ");
            $sucursales = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } elseif (!empty($empresas_ids)) {
            $placeholders = implode(',', array_fill(0, count($empresas_ids), '?'));
            $stmt = $pdo->prepare("
                SELECT s.id, s.nombre, s.direccion, s.activo, s.created_at, e.nombre AS empresa 
                FROM sucursales s 
                INNER JOIN empresas e ON s.empresa_id = e.id 
                WHERE s.empresa_id IN ($placeholders) 
                ORDER BY e.nombre, s.nombre
            ");
            $stmt->execute($empresas_ids);
            $sucursales = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        foreach ($sucursales as $s): ?>
            <tr>
                <td><?= htmlspecialchars($s['empresa']) ?></td>
                <td><?= htmlspecialchars($s['nombre']) ?></td>
                <td><?= htmlspecialchars($s['direccion'] ?: '—') ?></td>
                <td>
                    <span class="badge bg-<?= $s['activo'] ? 'success' : 'danger' ?>">
                        <?= $s['activo'] ? 'Activa' : 'Inactiva' ?>
                    </span>
                </td>
                <td><?= date('d/m/Y', strtotime($s['created_at'])) ?></td>
                <td>
                    <a href="?action=sucursal_edit&id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-warning">Editar</a>
                    <form method="post" action="?action=sucursales" style="display:inline;" 
                          onsubmit="return confirm('¿<?= $s['activo'] ? 'Desactivar' : 'Activar' ?> esta sucursal?')">
                        <input type="hidden" name="action" value="toggle_activo">
                        <input type="hidden" name="sucursal_id" value="<?= $s['id'] ?>">
                        <button type="submit" class="btn btn-sm <?= $s['activo'] ? 'btn-outline-secondary' : 'btn-outline-success' ?>">
                            <?= $s['activo'] ? 'Desactivar' : 'Activar' ?>
                        </button>
                    </form>
                    <?php if ($_SESSION['rol'] === 'admin'): ?>
                        <form method="post" action="?action=sucursales" style="display:inline;" 
                              onsubmit="return confirm('¿Eliminar sucursal permanentemente?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="sucursal_id" value="<?= $s['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($sucursales)): ?>
            <tr><td colspan="6" class="text-center py-4">No hay sucursales para mostrar</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>