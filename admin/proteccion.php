<?php
// ================================================
// PROTECCION.PHP - BLOQUE ÚNICO ANTI-BYPASS
// ================================================
// Este archivo se incluye al principio de TODOS los archivos del admin

session_start();

if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['email'])) {
    header("Location: login_oauth.php");
    exit;
}

// Opcional: verificar que el usuario siga activo
$stmt = $pdo->prepare("SELECT activo FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['usuario_id']]);
if (!$stmt->fetchColumn()) {
    session_destroy();
    header("Location: login_oauth.php?error=cuenta_inactiva");
    exit;
}
?>
