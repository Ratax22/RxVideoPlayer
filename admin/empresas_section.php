<h1 class="mb-4">Empresas</h1>

<?php if (isset($_SESSION['flash'])): ?>
<div class="alert alert-<?= $_SESSION['flash']['type'] ?? 'info' ?> alert-dismissible fade show">
    <?= htmlspecialchars($_SESSION['flash']['message'] ?? '') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['flash']); endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Listado de empresas</h5>
    <a href="?action=empresa_edit" class="btn btn-primary btn-sm">+ Nueva Empresa</a>
</div>

<div class="table-responsive">
    <table class="table table-hover table-bordered align-middle">
        <thead class="table-dark">
            <tr>
                <th>Nombre</th>
                <th>CUIT</th>
                <th>Estado</th>
                <th>Creada</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php 
        $empresas = [];
        $empresas_ids = getEmpresasAcceso($pdo, $_SESSION['usuario_id'], $_SESSION['rol']);
        if ($_SESSION['rol'] === 'admin') {
            $stmt = $pdo->query("SELECT id, nombre, cuit, activo, created_at FROM empresas ORDER BY nombre");
            $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } elseif (!empty($empresas_ids)) {
            $placeholders = implode(',', array_fill(0, count($empresas_ids), '?'));
            $stmt = $pdo->prepare("SELECT id, nombre, cuit, activo, created_at FROM empresas WHERE id IN ($placeholders) ORDER BY nombre");
            $stmt->execute($empresas_ids);
            $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        foreach ($empresas as $e): ?>
            <tr>
                <td><?= htmlspecialchars($e['nombre']) ?></td>
                <td><?= htmlspecialchars($e['cuit'] ?: '—') ?></td>
                <td>
                    <span class="badge bg-<?= $e['activo'] ? 'success' : 'danger' ?>">
                        <?= $e['activo'] ? 'Activa' : 'Inactiva' ?>
                    </span>
                </td>
                <td><?= date('d/m/Y', strtotime($e['created_at'])) ?></td>
                <td>
                    <a href="?action=empresa_edit&id=<?= $e['id'] ?>" class="btn btn-sm btn-outline-warning">Editar</a>
                    <form method="post" action="?action=empresas" style="display:inline;" 
                          onsubmit="return confirm('¿<?= $e['activo'] ? 'Desactivar' : 'Activar' ?> esta empresa?')">
                        <input type="hidden" name="action" value="toggle_activo">
                        <input type="hidden" name="empresa_id" value="<?= $e['id'] ?>">
                        <button type="submit" class="btn btn-sm <?= $e['activo'] ? 'btn-outline-secondary' : 'btn-outline-success' ?>">
                            <?= $e['activo'] ? 'Desactivar' : 'Activar' ?>
                        </button>
                    </form>
                    <?php if ($_SESSION['rol'] === 'admin'): ?>
                        <form method="post" action="?action=empresas" style="display:inline;" 
                              onsubmit="return confirm('¿Eliminar empresa permanentemente?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="empresa_id" value="<?= $e['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($empresas)): ?>
            <tr><td colspan="5" class="text-center py-4">No hay empresas para mostrar</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>