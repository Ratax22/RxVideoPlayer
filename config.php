<?php

// ================================================
// CONFIG.PHP - ÚNICO ARCHIVO DE CONFIGURACIÓN
// ================================================

// ================= SECCIÓN 1: CONEXIÓN BASE DE DATOS =================
$host = 'localhost';
$db   = 'rx_video_player';
$user = 'Rx_Video_Player';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("❌ Error DB: " . $e->getMessage());
}

// ================= SECCIÓN 2: RUTAS DE CARPETAS =================
define('ROOT_DIR',      __DIR__ . '/');
define('VIDEO_DIR',     ROOT_DIR . 'videos/');
define('IMAGE_DIR',     ROOT_DIR . 'images/');
define('THUMB_DIR',     IMAGE_DIR . 'thumbs/');
define('BG_DIR',        IMAGE_DIR . 'backgrounds/');
define('UPLOAD_DIR',    ROOT_DIR . 'uploads/');

// ================= SECCIÓN 3: FFmpeg (multiplataforma) =================
// En Linux: solo "ffmpeg"
// En Windows/XAMPP: ruta completa al ffmpeg.exe
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    define('FFMPEG_PATH', 'C:\\xampp\\ffmpeg\\bin\\ffmpeg.exe'); // ← CAMBIA SI ES DIFERENTE
} else {
    define('FFMPEG_PATH', 'ffmpeg');
}

// ================= SECCIÓN 4: COOKIES Y SEGURIDAD =================
define('COOKIE_NAME',   'client_key');
define('COOKIE_DAYS',   365);   // cookie permanente

// ================= SECCIÓN 5: TIEMPOS =================
define('HEARTBEAT_MIN', 5);     // minutos para considerar inactivo
define('POLL_INTERVAL', 30);    // segundos que el cliente consulta si hay actualización

// ================= FIN CONFIG =================

// ================= SECCIÓN 6: SEGURIDAD ADMIN =================
define('ADMIN_USER',     'user');                  // Cambialo a lo que quieras
define('ADMIN_PASS_HASH', password_hash('$2y$12$z8/AoWe9I1lrK3uy/HRKueYjOjssHm0vFgxvojuG5AfxAsBSHDtMa', PASSWORD_DEFAULT));
// Ejemplo: password_hash('12345678', PASSWORD_DEFAULT)
// Guardá el hash generado y pegalo acá (no la contraseña en plano)

// ================= SECCIÓN 7: GOOGLE OAUTH =================
define('GOOGLE_CLIENT_ID',     '');
define('GOOGLE_CLIENT_SECRET', '');
define('GOOGLE_REDIRECT_URI',  'https://videoplayer.ratax.com.ar/admin/callback.php');
define('GOOGLE_AUTH_URL',      'https://accounts.google.com/o/oauth2/v2/auth');
define('GOOGLE_TOKEN_URL',     'https://oauth2.googleapis.com/token');
define('GOOGLE_USERINFO_URL',  'https://www.googleapis.com/oauth2/v3/userinfo');

?>
