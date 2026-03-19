<?php

session_start();
require_once '../config.php';       // conexión + constantes
require_once 'proteccion.php';      // chequeo de sesión y rol

// Proteger: si no está logueado → redirigir a login
//if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    //header("Location: login.php");
    //exit;
//    echo "error";
//}

// Mensaje flash si viene de alguna acción
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Panel Admin</title>
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
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">Cerrar sesión (<?= htmlspecialchars($_SESSION['admin_user'] ?? 'Admin') ?>)</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container py-5">

    <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?? 'info' ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($flash['message'] ?? '') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <h1 class="mb-4 text-center">Bienvenido al Panel</h1>

    <div class="row g-4">
        <div class="col-md-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <h5 class="card-title">Videos</h5>
                    <p class="card-text">Subir, editar, rotar, thumbnails y eliminar videos</p>
                    <a href="video.php" class="btn btn-primary">Ir a Videos</a>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <h5 class="card-title">Clientes / Dispositivos</h5>
                    <p class="card-text">Gestionar TV-Box, orientación, fondos y estado</p>
                    <a href="clientes.php" class="btn btn-primary">Ir a Clientes</a>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <h5 class="card-title">Usuarios</h5>
                    <p class="card-text">Gestion de usuarios y sucursales</p>
                    <a href="usuarios.php" class="btn btn-primary">Administrar Usuarios</a>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
