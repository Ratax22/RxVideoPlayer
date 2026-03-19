<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Publicidad - Panel Principal</title>
<style>
body { font-family: Arial, sans-serif; margin:40px; background:#f8f9fa; }
h1 { color:#333; }
.menu { display:grid; grid-template-columns:repeat(auto-fit,minmax(250px,1fr)); gap:20px; }
.card { background:white; padding:30px; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.1); text-align:center; }
.card a { display:block; margin-top:15px; font-size:18px; text-decoration:none; color:#007bff; }
</style>
</head>
<body>
<h1>🎥 Sistema Publicidad TV-Boxes</h1>
<div class="menu">
<div class="card">
<h2>📹 Gestión de Videos</h2>
<a href="admin/video.php">→ Ir al módulo</a>
</div>
<div class="card">
<h2>📺 Gestión de Clientes</h2>
<a href="admin/clientes.php">→ Ir al módulo</a>
</div>
<div class="card">
<h2>🔗 Asignar Videos</h2>
<a href="admin/assign.php">→ Ir al módulo</a>
</div>
</div>
<p><small>Proyecto en construcción – Paso 1 completado ✅</small></p>
</body>
</html>
