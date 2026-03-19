<?php
// ================================================
// SECCIÓN 1: INCLUDES Y PROTECCIÓN
// ================================================
session_start();
require_once '../config.php';
require_once 'proteccion.php';  // ← tu bloque de protección

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
$sucursales_asignadas = [];

// ================================================
// SECCIÓN 3: PROCESAR ELIMINAR (GET ?delete=ID)
// ================================================
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $pdo->prepare("DELETE FROM clients WHERE id = ?")->execute([$id]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Cliente eliminado'];
        header("Location: clients.php");
        exit;
    } catch (PDOException $e) {
        $errors[] = "Error al eliminar: " . $e->getMessage();
    }
}

// ================================================
// SECCIÓN 4: PROCESAR FORMULARIO (POST - guardar)
// ================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id             = (int)($_POST['id'] ?? 0);
    $name           = trim($_POST['name'] ?? '');
    $client_key     = trim($_POST['client_key'] ?? '');
    $orientation    = $_POST['orientation'] ?? 'horizontal';
    $background     = trim($_POST['background'] ?? '');
    $sucursales     = $_POST['sucursales'] ?? [];

    // Validaciones
    if (empty($name))               $errors[] = "El nombre es obligatorio.";
    if (empty($client_key))         $errors[] = "El client_key es obligatorio.";
    elseif (strlen($client_key) !== 32 || !ctype_alnum($client_key)) {
        $errors[] = "Client_key debe ser 32 caracteres alfanuméricos.";
    }

    // Chequear unicidad de client_key
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM clients WHERE client_key = ? AND id != ?");
        $stmt->execute([$client_key, $id]);
        if ($stmt->fetch()) {
            $errors[] = "Ese client_key ya está en uso.";
        }
    }

    if (empty($errors)) {
        try {
            if ($id > 0) {
                // UPDATE
                $stmt = $pdo->prepare("
                    UPDATE clients SET 
                        name = ?, client_key = ?, orientation = ?, background = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $client_key, $orientation, $background, $id]);
            } else {
                // INSERT
                $stmt = $pdo->prepare("
                    INSERT INTO clients 
                    (name, client_key, orientation, background, active, playlist_version) 
                    VALUES (?, ?, ?, ?, 1, 0)
                ");
                $stmt->execute([$name, $client_key, $orientation, $background]);
                $id = $pdo->lastInsertId();
            }

            // ================================================
            // Guardar relaciones con sucursales
            // ================================================
            $pdo->prepare("DELETE FROM client_sucursal WHERE client_id = ?")->execute([$id]);

            if (!empty($sucursales)) {
                $stmt = $pdo->prepare("INSERT INTO client_sucursal (client_id, sucursal_id) VALUES (?, ?)");
                foreach ($sucursales as $suc_id) {
                    $stmt->execute([$id, (int)$suc_id]);
                }
            }

            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Cliente guardado correctamente'];
            header("Location: clients.php");
            exit;

        } catch (PDOException $e) {
            $errors[] = "Error en base de datos: " . $e->getMessage();
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
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC) ?: $cliente;

    // Cargar sucursales asignadas
    $stmt = $pdo->prepare("SELECT sucursal_id FROM client_sucursal WHERE client_id = ?");
    $stmt->execute([$id]);
    $sucursales_asignadas = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// ================================================
// SECCIÓN 6: LISTADO DE SUCURSALES PARA EL SELECT
// ================================================
$sucursales = $pdo->query("
    SELECT s.id, s.nombre, e.nombre AS empresa 
    FROM sucursales s 
    INNER JOIN empresas e ON s.empresa_id = e.id 
    WHERE s.activo = 1 
    ORDER BY e.nombre, s.nombre
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $cliente['id'] ? 'Editar' : 'Nuevo' ?> Cliente</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-5" style="max-width: 700px;">

    <h2 class="mb-4"><?= $cliente['id'] ? 'Editar Cliente' : 'Nuevo Cliente' ?></h2>

    <?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $err): ?>
                <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="id" value="<?= htmlspecialchars($cliente['id']) ?>">

        <div class="mb-3">
            <label class="form-label fw-bold">Nombre identificador</label>
            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($cliente['name']) ?>" required autofocus>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">Client Key (32 caracteres)</label>
            <input type="text" name="client_key" class="form-control font-monospace" 
                   value="<?= htmlspecialchars($cliente['client_key']) ?>" 
                   maxlength="32" pattern="[A-Za-z0-9]{32}" required <?= $cliente['id'] ? 'readonly' : '' ?>>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">Orientación</label>
            <select name="orientation" class="form-select">
                <option value="horizontal" <?= $cliente['orientation'] === 'horizontal' ? 'selected' : '' ?>>Horizontal</option>
                <option value="vertical"   <?= $cliente['orientation'] === 'vertical'   ? 'selected' : '' ?>>Vertical</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">Background (ruta relativa)</label>
            <input type="text" name="background" class="form-control" 
                   value="<?= htmlspecialchars($cliente['background']) ?>" 
                   placeholder="images/backgrounds/sucursal1.jpg">
        </div>

        <!-- Sucursales -->
        <div class="mb-4">
            <label class="form-label fw-bold">Sucursales asociadas</label>
            <select name="sucursales[]" class="form-select" multiple size="8">
                <?php foreach ($sucursales as $s): 
                    $selected = in_array($s['id'], $sucursales_asignadas) ? 'selected' : '';
                ?>
                    <option value="<?= $s['id'] ?>" <?= $selected ?>>
                        <?= htmlspecialchars($s['empresa'] . ' → ' . $s['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="form-text text-muted">Mantén Ctrl (o Cmd en Mac) para seleccionar varias sucursales</div>
        </div>

        <div class="d-flex gap-2 justify-content-end">
            <a href="clients.php" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-success px-4">Guardar</button>
        </div>
    </form>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>