<?php
session_start();
require_once '../config.php';

// Si ya está logueado → redirigir al hub
if (isset($_SESSION['usuario_id']) && isset($_SESSION['rol'])) {
    header("Location: index.php?action=dashboard");
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if (empty($email) || empty($pass)) {
        $errors[] = 'Email y contraseña son obligatorios';
    } else {
        $stmt = $pdo->prepare("
            SELECT id, email, nombre, activo, rol, password_hash 
            FROM usuarios 
            WHERE email = ?
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && $user['activo'] && password_verify($pass, $user['password_hash'])) {
            $_SESSION['usuario_id'] = $user['id'];
            $_SESSION['email']      = $user['email'];
            $_SESSION['nombre']     = $user['nombre'];
            $_SESSION['rol']        = $user['rol'];
            header("Location: index.php?action=dashboard");
            exit;
        } else {
            $errors[] = 'Credenciales incorrectas o cuenta inactiva';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-light">
<div class="container" style="max-width:400px; margin-top:120px;">
    <div class="card bg-dark border-light shadow">
        <div class="card-header text-center py-3">
            <h4>Login al Panel</h4>
        </div>
        <div class="card-body">
            <?php if ($errors): ?>
                <div class="alert alert-danger">
                    <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control bg-secondary text-white" required autofocus>
                </div>
                <div class="mb-4">
                    <label class="form-label">Contraseña</label>
                    <input type="password" name="password" class="form-control bg-secondary text-white" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Ingresar</button>
            </form>

            <div class="text-center mt-4">
                <a href="login_oauth.php" class="text-light">Ingresar con Google</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>