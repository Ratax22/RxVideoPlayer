<?php
// ================================================
// index.php - HUB CENTRAL DEL PANEL ADMIN
// ================================================

session_start();
require_once '../config.php';
require_once 'proteccion.php';

// ================================================
// MENÚ PRINCIPAL VISIBLE (solo categorías principales)
// ================================================
$menu_items = [
    'dashboard'     => ['label' => 'Dashboard',      'icon' => 'bi-house-door-fill'],
    'videos'        => ['label' => 'Videos',         'icon' => 'bi-film'],
    'clientes'      => ['label' => 'Dispositivos',   'icon' => 'bi-tv'],
    'usuarios'      => ['label' => 'Usuarios',       'icon' => 'bi-people-fill'],
    'empresas'      => ['label' => 'Empresas',       'icon' => 'bi-building-fill'],
    'sucursales'    => ['label' => 'Sucursales',     'icon' => 'bi-shop-window'],
];

// Acciones internas (ocultas del menú, pero el router las acepta)
$internal_actions = [
    'cliente_nuevo', 'cliente_edit', 'cliente_delete',
    'video_nuevo', 'video_edit', 'video_delete',
    'sucursal_nueva', 'sucursal_edit', 'sucursal_delete',
    'empresa_nueva', 'empresa_edit', 'empresa_delete',
    'usuario_nuevo', 'usuario_edit', 'usuario_delete',
    // Agregá acá cualquier otra acción oculta que tengas
];

// Obtener acción solicitada
$action = $_GET['action'] ?? 'dashboard';

// Validar acción: si no está en menú ni en internas → fallback a dashboard
if (!isset($menu_items[$action]) && !in_array($action, $internal_actions)) {
    $action = 'dashboard';
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
        .sidebar {
            min-height: 100vh;
            background: #212529;
            padding-top: 1rem;
        }
        .sidebar .nav-link {
            color: #adb5bd;
            border-radius: 0.375rem;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background: #495057;
        }
        .content {
            padding-top: 1rem;
        }
        @media (max-width: 991.98px) {
            .sidebar {
                min-height: auto;
            }
        }
    </style>
</head>
<body class="bg-light">

<!-- Navbar superior fija -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="?action=dashboard">Panel Publicidad</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
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
                if (in_array($_SESSION['rol'], $item['roles'] ?? ['admin','dueño','supervisor','empleado'])): 
                    $active = ($action === $key) ? 'active' : '';
            ?>
                <li class="nav-item mb-1">
                    <a class="nav-link <?= $active ?>" href="?action=<?= $key ?>">
                        <i class="bi <?= $item['icon'] ?> me-2"></i> <?= $item['label'] ?>
                    </a>
                </li>
            <?php endif; endforeach; ?>
        </ul>
    </div>

    <!-- Contenido principal -->
    <main class="content col-12 col-lg-10 p-4">
        <?php
        // Router: cargar la sección correspondiente
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