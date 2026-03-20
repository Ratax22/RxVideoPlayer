<?php
// admin/index.php - HUB CENTRAL DEL PANEL

session_start();
require_once '../config.php';
require_once 'proteccion.php';

// ================================================
// SECCIÓN: MENÚ PRINCIPAL (acciones permitidas según rol)
// ================================================
$menu_items = [
    'dashboard'  => ['label' => 'Dashboard',     'icon' => 'bi-house-door',    'roles' => ['admin','dueño','supervisor','empleado']],
    'videos'     => ['label' => 'Videos',        'icon' => 'bi-film',           'roles' => ['admin','dueño','supervisor','empleado']],
    'video_edit'     => ['label' => 'Videos Editar',        'icon' => 'bi-film',           'roles' => ['admin','dueño','supervisor','empleado']],
    'video_upload'     => ['label' => 'Videos Subir',        'icon' => 'bi-film',           'roles' => ['admin','dueño','supervisor','empleado']],
    'video_delete'     => ['label' => 'Videos Eliminar',        'icon' => 'bi-film',           'roles' => ['admin','dueño','supervisor','empleado']],
    'clientes'   => ['label' => 'Dispositivos',  'icon' => 'bi-tv',             'roles' => ['admin','dueño','supervisor','empleado']],
    'cliente_edit'   => ['label' => 'Dispositivos Editar',  'icon' => 'bi-tv',             'roles' => ['admin','dueño','supervisor','empleado']],
    'usuarios'   => ['label' => 'Usuarios',      'icon' => 'bi-people',         'roles' => ['admin','dueño','supervisor']],
    'usuario_edit'   => ['label' => 'Usuarios Editar',      'icon' => 'bi-people',         'roles' => ['admin','dueño','supervisor']],
    'empresas'   => ['label' => 'Empresas',      'icon' => 'bi-building',       'roles' => ['admin','dueño']],
    'empresas_editar'   => ['label' => 'Empresas Editar',      'icon' => 'bi-building',       'roles' => ['admin','dueño']],
    'sucursales' => ['label' => 'Sucursales',    'icon' => 'bi-shop',           'roles' => ['admin','dueño']],
    'sucursales_edit' => ['label' => 'Sucursales Editar',    'icon' => 'bi-shop',           'roles' => ['admin','dueño']],
];

$action = $_GET['action'] ?? 'dashboard';
if (!isset($menu_items[$action]) || !in_array($rol, $menu_items[$action]['roles'])) {
    $action = 'dashboard'; // fallback seguro
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Publicidad</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .sidebar { min-height: 100vh; background: #343a40; }
        .content { min-height: 100vh; }
    </style>
</head>
<body>

<!-- Navbar superior fija -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="?action=dashboard">Panel Publicidad</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">Salir (<?= htmlspecialchars($_SESSION['nombre'] ?? 'Usuario') ?>)</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="d-flex" style="padding-top: 56px;"> <!-- Espacio para navbar fija -->

    <!-- Sidebar (menú hamburguesa en móvil, visible en desktop) -->
    <div class="sidebar d-none d-lg-block col-lg-2 p-3">
        <ul class="nav flex-column">
            <?php foreach ($menu_items as $key => $item): 
                if (in_array($rol, $item['roles'])): 
                    $active = ($action === $key) ? 'active' : '';
            ?>
                <li class="nav-item">
                    <a class="nav-link text-white <?= $active ?>" href="?action=<?= $key ?>">
                        <i class="bi <?= $item['icon'] ?> me-2"></i> <?= $item['label'] ?>
                    </a>
                </li>
            <?php endif; endforeach; ?>
        </ul>
    </div>

    <!-- Contenido principal -->
    <main class="content col-12 col-lg-10 p-4">
        <?php
        // Router simple: incluir la sección según action
        $section_file = __DIR__ . '/' . $action . '_section.php';
        if (file_exists($section_file)) {
            include $section_file;
        } else {
            echo '<div class="alert alert-warning">Sección no encontrada: ' . htmlspecialchars($action) . '</div>';
        }
        ?>
    </main>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>