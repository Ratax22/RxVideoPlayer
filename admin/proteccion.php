<?php
// admin/proteccion.php
// Protección centralizada - incluir al inicio de TODOS los archivos admin

session_start();

$current_page = basename($_SERVER['PHP_SELF']);
if ($current_page === 'login.php' || $current_page === 'callback.php') {
    // No aplicar protección en login ni callback
    return;
}

if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['rol'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['usuario_id'];
$rol     = $_SESSION['rol'];

// Verificar que siga activo
$stmt = $pdo->prepare("SELECT activo FROM usuarios WHERE id = ?");
$stmt->execute([$user_id]);
if (!$stmt->fetchColumn()) {
    session_destroy();
    header("Location: login.php?error=cuenta_inactiva");
    exit;
}

// Función auxiliar: sucursales accesibles
function getSucursalesAcceso($pdo, $user_id, $rol) {
    if ($rol === 'admin') {
        // Admin ve TODAS las sucursales
        $stmt = $pdo->query("SELECT id FROM sucursales WHERE activo = 1");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    if ($rol === 'dueño') {
        // Dueño ve sucursales de sus empresas
        $stmt = $pdo->prepare("
            SELECT s.id 
            FROM sucursales s
            INNER JOIN usuario_empresa ue ON s.empresa_id = ue.empresa_id
            WHERE ue.usuario_id = ?
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // Supervisor y empleado: solo las asignadas directamente
    $stmt = $pdo->prepare("SELECT sucursal_id FROM usuario_sucursal WHERE usuario_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Función auxiliar: empresas accesibles (para dueños y admin)
function getEmpresasAcceso($pdo, $user_id, $rol) {
    if ($rol === 'admin') {
        $stmt = $pdo->query("SELECT id FROM empresas WHERE activo = 1");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    $stmt = $pdo->prepare("SELECT empresa_id FROM usuario_empresa WHERE usuario_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>