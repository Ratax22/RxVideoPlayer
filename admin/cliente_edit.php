<?php
// ================================================
// SECCIÓN 1: INCLUDES Y CONFIGURACIÓN
// ================================================
require_once 'proteccion.php';
require_once '../config.php';

// ================================================
// SECCIÓN 2: VARIABLES INICIALES
// ================================================
$errors = [];

$cliente = [
    'id'              => 0,
'name'            => '',
'client_key'      => '',
'orientation'     => 'horizontal',
'background'      => ''
];

// ================================================
// SECCIÓN 3: PROCESAR ELIMINAR
// ================================================
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $pdo->prepare("DELETE FROM clients WHERE id = ?")->execute([$id]);

        $_SESSION['flash'] = [
            'type'    => 'success',
            'message' => 'Cliente eliminado correctamente'
        ];
    } catch (PDOException $e) {
        $_SESSION['flash'] = [
            'type'    => 'danger',
            'message' => 'Error al eliminar: ' . $e->getMessage()
        ];
    }
}

// ================================================
// SECCIÓN 4: PROCESAR GUARDAR (POST)
// ================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id             = (int)($_POST['id'] ?? 0);
    $name           = trim($_POST['name'] ?? '');
    $client_key     = trim($_POST['client_key'] ?? '');
    $orientation    = $_POST['orientation'] ?? 'horizontal';
    $background     = trim($_POST['background'] ?? '');

    // Validaciones
    if (empty($name))               $errors[] = "El nombre es obligatorio.";
    if (empty($client_key))         $errors[] = "El client_key es obligatorio.";
    elseif (strlen($client_key) !== 32 || !ctype_alnum($client_key)) {
        $errors[] = "El client_key debe tener exactamente 32 caracteres alfanuméricos.";
    }

    // Verificar unicidad
    if (empty($errors) && $id === 0) {  // solo chequeamos al crear nuevo
        $stmt = $pdo->prepare("SELECT 1 FROM clients WHERE client_key = ?");
        $stmt->execute([$client_key]);
        if ($stmt->fetch()) {
            $errors[] = "Ese client_key ya existe. Genera uno nuevo.";
        }
    }

    if (empty($errors)) {
        try {
            if ($id > 0) {
                $stmt = $pdo->prepare("
                UPDATE clients SET name = ?, client_key = ?, orientation = ?, background = ?
                WHERE id = ?
                ");
                $stmt->execute([$name, $client_key, $orientation, $background, $id]);
            } else {
                $stmt = $pdo->prepare("
                INSERT INTO clients (name, client_key, orientation, background, active, playlist_version)
                VALUES (?, ?, ?, ?, 0, 0)
                ");
                $stmt->execute([$name, $client_key, $orientation, $background]);
            }

            $_SESSION['flash'] = [
                'type'    => 'success',
                'message' => $cliente['id'] > 0 ? 'Cliente actualizado correctamente' : 'Cliente creado correctamente'
            ];
        } catch (PDOException $e) {
            $_SESSION['flash'] = [
                'type'    => 'danger',
                'message' => 'Error al guardar: ' . $e->getMessage()
            ];
        }
    }
}

// ================================================
// SECCIÓN 5: CARGAR DATOS PARA EDICIÓN
// ================================================
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$id]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $cliente = $row;
    } else {
        $errors[] = "Cliente no encontrado.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $cliente['id'] > 0 ? 'Editar' : 'Nuevo' ?> Cliente</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.key-display { font-family: monospace; font-size: 1.1rem; letter-spacing: 1px; }
</style>
</head>
<body class="bg-light">
<?php
if (isset($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);  // Consumirlo para que no vuelva a aparecer
    ?>
    <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php } ?>

<div class="container py-5" style="max-width: 680px;">

<h2 class="mb-4"><?= $cliente['id'] > 0 ? 'Editar Cliente' : 'Nuevo Cliente' ?></h2>

<?php if ($errors): ?>
<div class="alert alert-danger">
<ul class="mb-0">
<?php foreach ($errors as $err): ?>
<li><?= htmlspecialchars($err) ?></li>
<?php endforeach; ?>
</ul>
</div>
<?php endif; ?>

<form method="post" id="clientForm">
<input type="hidden" name="id" value="<?= htmlspecialchars($cliente['id']) ?>">

<div class="mb-3">
<label class="form-label fw-bold">Nombre identificador</label>
<input type="text" name="name" class="form-control" value="<?= htmlspecialchars($cliente['name']) ?>" required autofocus>
</div>

<div class="mb-3">
<label class="form-label fw-bold">Client Key (32 caracteres)</label>
<div class="input-group">
<input type="text" name="client_key" id="client_key" class="form-control key-display"
value="<?= htmlspecialchars($cliente['client_key']) ?>"
maxlength="32" pattern="[A-Za-z0-9]{32}" required readonly>
<?php if ($cliente['id'] == 0): ?>
<button type="button" class="btn btn-outline-primary" id="generateKey">
Generar clave aleatoria
</button>
<?php endif; ?>
</div>
<div class="form-text">
<?= $cliente['id'] > 0
? 'No se puede modificar una vez creado (por seguridad).'
: 'Haz clic en el botón para generar una clave única de 32 caracteres.' ?>
</div>
</div>

<div class="mb-3">
<label class="form-label fw-bold">Orientación</label>
<select name="orientation" class="form-select">
<option value="horizontal" <?= $cliente['orientation'] === 'horizontal' ? 'selected' : '' ?>>Horizontal</option>
<option value="vertical"   <?= $cliente['orientation'] === 'vertical'   ? 'selected' : '' ?>>Vertical</option>
</select>
</div>

<div class="mb-4">
<label class="form-label fw-bold">Background (ruta)</label>
<input type="text" name="background" class="form-control"
value="<?= htmlspecialchars($cliente['background']) ?>"
placeholder="images/backgrounds/fondo-default.jpg">
</div>

<div class="d-flex gap-2 justify-content-end">
<a href="clientes.php" class="btn btn-secondary">Volver</a>
<button type="submit" class="btn btn-success px-4">Guardar</button>
</div>
</form>

</div>

<script>
// Generador de clave aleatoria (32 caracteres alfanuméricos)
document.getElementById('generateKey')?.addEventListener('click', function() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
let key = '';
for (let i = 0; i < 32; i++) {
    key += chars.charAt(Math.floor(Math.random() * chars.length));
}
document.getElementById('client_key').value = key;
});
</script>

</body>
</html>
