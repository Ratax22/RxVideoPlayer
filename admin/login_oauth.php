<?php
session_start();
require_once '../config.php';

// Si ya está logueado → redirigir al dashboard
if (isset($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit;
}

$login_url = GOOGLE_AUTH_URL . '?' . http_build_query([
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'access_type'   => 'online',
    'prompt'        => 'select_account'
]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Panel Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .login-box { max-width: 400px; margin: 120px auto; }
    </style>
</head>
<body class="bg-dark text-light">

<div class="container login-box">
    <div class="card bg-dark border-light shadow">
        <div class="card-header text-center py-4">
            <h3>Panel de Administración</h3>
            <p class="text-muted">Inicia sesión con tu cuenta de Google</p>
        </div>
        <div class="card-body text-center py-5">
            <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($_GET['error'] === 'cuenta_inactiva' ? 'Cuenta inactiva. Contacta al administrador.' : 'Error al iniciar sesión') ?>
            </div>
            <?php endif; ?>

            <a href="<?= htmlspecialchars($login_url) ?>" class="btn btn-lg btn-danger w-100">
                <i class="bi bi-google me-2"></i> Ingresar con Google
            </a>
            <p class="mt-4 text-muted small">
                Solo usuarios autorizados pueden acceder.
            </p>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</body>
</html>