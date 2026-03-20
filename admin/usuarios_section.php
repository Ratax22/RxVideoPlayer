<h1 class="mb-4">Administración de Usuarios</h1>

<?php if (isset($_SESSION['flash'])): ?>
<div class="alert alert-<?= $_SESSION['flash']['type'] ?? 'info' ?> alert-dismissible fade show">
    <?= htmlspecialchars($_SESSION['flash']['message'] ?? '') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['flash']); endif; ?>

<?php if ($_SESSION['rol'] === 'admin' || $_SESSION['rol'] === 'dueño'): ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Listado de usuarios</h5>
    <a href="?action=usuario_edit" class="btn btn-primary btn-sm">+ Nuevo Usuario</a>
</div>
<?php endif; ?>

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
        <?php
        $usuarios = [];
        if ($_SESSION['rol'] === 'admin') {
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
            $empresas_ids = getEmpresasAcceso($pdo, $_SESSION['usuario_id'], $_SESSION['rol']);
            if (!empty($empresas_ids)) {
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

        foreach ($usuarios as $u): ?>
            <tr>
                <td><?= htmlspecialchars($u['nombre']) ?></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td>
                    <span class="badge bg-<?= $u['rol']==='admin'?'danger':($u['rol']==='dueño'?'warning':'primary') ?>">
                        <?= ucfirst($u['rol']) ?>
                    </span>
                </td>
                <td><?= htmlspecialchars($u['empresas'] ?: '—') ?></td>
                <td>
                    <span class="badge bg-<?= $u['activo'] ? 'success' : 'danger' ?>">
                        <?= $u['activo'] ? 'Activo' : 'Inactivo' ?>
                    </span>
                </td>
                <td>
                    <?php if ($_SESSION['rol'] === 'admin' || $_SESSION['rol'] === 'dueño' || ($_SESSION['rol'] === 'supervisor' && $u['rol'] === 'empleado')): ?>
                        <a href="?action=usuario_edit&id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-warning">Editar</a>
                        <form method="post" action="?action=usuarios" style="display:inline;" 
                              onsubmit="return confirm('¿<?= $u['activo'] ? 'Deshabilitar' : 'Habilitar' ?> este usuario?')">
                            <input type="hidden" name="action" value="toggle_activo">
                            <input type="hidden" name="usuario_id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn btn-sm <?= $u['activo'] ? 'btn-outline-secondary' : 'btn-outline-success' ?>">
                                <?= $u['activo'] ? 'Deshabilitar' : 'Habilitar' ?>
                            </button>
                        </form>
                        <?php if ($_SESSION['rol'] === 'admin' || $_SESSION['rol'] === 'dueño'): ?>
                            <form method="post" action="?action=usuarios" style="display:inline;" 
                                  onsubmit="return confirm('¿Eliminar usuario permanentemente?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="usuario_id" value="<?= $u['id'] ?>">
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