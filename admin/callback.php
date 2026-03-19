<?php
session_start();
require_once '../config.php';

if (!isset($_GET['code'])) {
    header("Location: login_oauth.php?error=acceso_denegado");
    exit;
}

$code = $_GET['code'];

// Paso 1: Intercambiar code por tokens
$token_response = file_get_contents(GOOGLE_TOKEN_URL, false, stream_context_create([
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query([
            'code'          => $code,
            'client_id'     => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri'  => GOOGLE_REDIRECT_URI,
            'grant_type'    => 'authorization_code'
        ])
    ]
]));

$token_data = json_decode($token_response, true);

if (isset($token_data['error'])) {
    header("Location: login_oauth.php?error=token_error");
    exit;
}

$access_token = $token_data['access_token'];

// Paso 2: Obtener info del usuario
$user_response = file_get_contents(GOOGLE_USERINFO_URL . '?access_token=' . $access_token);
$user_data = json_decode($user_response, true);

if (!$user_data || empty($user_data['sub']) || empty($user_data['email'])) {
    header("Location: login_oauth.php?error=info_error");
    exit;
}

// Datos del usuario Google
$google_id = $user_data['sub'];
$email     = $user_data['email'];
$nombre    = $user_data['name'] ?? explode('@', $email)[0];

try {
    // Buscar o crear usuario
    $stmt = $pdo->prepare("SELECT id, activo FROM usuarios WHERE oauth_id = ? OR email = ?");
    $stmt->execute([$google_id, $email]);
    $usuario = $stmt->fetch();

    if ($usuario) {
        if (!$usuario['activo']) {
            header("Location: login_oauth.php?error=cuenta_inactiva");
            exit;
        }
        $user_id = $usuario['id'];
    } else {
        // Crear nuevo usuario (sin empresa/sucursal por defecto → admin lo asigna después)
        $stmt = $pdo->prepare("
            INSERT INTO usuarios (oauth_id, email, nombre, activo)
            VALUES (?, ?, ?, 1)
        ");
        $stmt->execute([$google_id, $email, $nombre]);
        $user_id = $pdo->lastInsertId();
    }

    // Sesión
    $_SESSION['usuario_id']    = $user_id;
    $_SESSION['email']         = $email;
    $_SESSION['nombre']        = $nombre;

    header("Location: index.php");
    exit;

} catch (PDOException $e) {
    error_log("Error en callback: " . $e->getMessage());
    header("Location: login_oauth.php?error=bd_error");
    exit;
}