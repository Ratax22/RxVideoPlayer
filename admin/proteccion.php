<?php
// admin/proteccion.php
// Protección centralizada - incluir al inicio de TODOS los archivos admin

session_start();

if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['rol'])) {
    header("Location: login.php");   // o login_oauth.php según preferencia
    exit;
}

$user_id = (int)$_SESSION['usuario_id'];
$rol     = $_SESSION['rol'];

// Verificar que el usuario siga activo
$stmt = $pdo->prepare("SELECT activo FROM usuarios WHERE id = ?");
$stmt->execute([$user_id]);
if (!$stmt->fetchColumn()) {
    session_destroy();
    header("Location: login.php?error=cuenta_inactiva");
    exit;
}

// Función auxiliar: obtener sucursales accesibles por el usuario actual
function getSucursalesAcceso($pdo, $user_id, $rol) {
    if ($rol === 'dueño') {
        // Dueño ve todas las sucursales de sus empresas
        $stmt = $pdo->prepare("
            SELECT s.id 
            FROM sucursales s
            INNER JOIN usuario_empresa ue ON s.empresa_id = ue.empresa_id
            WHERE ue.usuario_id = ?
        ");
        $stmt->execute([$user_id]);
    } else {
        // Supervisor y empleado ven solo las sucursales asignadas directamente
        $stmt = $pdo->prepare("SELECT sucursal_id FROM usuario_sucursal WHERE usuario_id = ?");
        $stmt->execute([$user_id]);
    }
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>