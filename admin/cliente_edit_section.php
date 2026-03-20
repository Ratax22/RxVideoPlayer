<?php
// ================================================
// cliente_edit_section.php
// Maneja tanto nuevo (id=0) como edición (id=XXX)
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

// ID del cliente (edición o 0 para nuevo)
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ================================================
// CARGAR DATOS SI ES EDICIÓN + CHEQUEO PERMISO
// ================================================
if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$id]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC) ?: $cliente;

    if (!$cliente['id']) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Cliente no encontrado.'];
        header("Location: ?action=clientes");
        exit;
    }

    // Chequear permiso sobre este cliente
    $sucursales_usuario = getSucursalesAcceso($pdo, $_SESSION['usuario_id'], $_SESSION['rol']);
    $stmt = $pdo->prepare("SELECT sucursal_id FROM client_sucursal WHERE client_id = ?");
    $stmt->execute([$id]);
    $sucursales_cliente = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $tiene_acceso = $_SESSION['rol'] === 'admin' || 
                    array_intersect($sucursales_usuario, $sucursales_cliente) ||
                    empty($sucursales_cliente);

    if (!$tiene_acceso) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'No tienes permiso para editar este cliente.'];
        header("Location: ?action=clientes");
        exit;
    }

    // Sucursales asignadas
    $sucursales_asignadas = $sucursales_cliente;
}

// ================================================
// SUCURSALES QUE PUEDE ASIGNAR ESTE USUARIO
// ================================================
$sucursales = [];
$sucursales_ids_permitidas = getSucursalesAcceso($pdo, $_SESSION['usuario_id'], $_SESSION['rol']);

if ($_SESSION['rol'] === 'admin') {
    $sucursales = $pdo->query("
        SELECT s.id, s.nombre, e.nombre AS empresa 
        FROM sucursales s 
        INNER JOIN empresas e ON s.empresa_id = e.id 
        WHERE s.activo = 1 
        ORDER BY e.nombre, s.nombre
    ")->fetchAll(PDO::FETCH_ASSOC);
} elseif (!empty($sucursales_ids_permitidas)) {
    $placeholders = implode(',', array_fill(0, count($sucursales_ids_permitidas), '?'));
    $stmt = $pdo->prepare("
        SELECT s.id, s.nombre, e.nombre AS empresa 
        FROM sucursales s 
        INNER JOIN empresas e ON s.empresa_id = e.id 
        WHERE s.id IN ($placeholders) AND s.activo = 1 
        ORDER BY e.nombre, s.nombre
    ");
    $stmt->execute($sucursales_ids_permitidas);
    $sucursales = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ================================================
// PROCESAR FORMULARIO (POST)
// ================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id             = (int)($_POST['id'] ?? 0);
    $name           = trim($_POST['name'] ?? '');
    $client_key     = trim($_POST['client_key'] ?? '');
    $orientation    = $_POST['orientation'] ?? 'horizontal';
    $background     = trim($_POST['background'] ?? '');
    $sucursales_post = $_POST['sucursales'] ?? [];

    // Validaciones
    if (empty($name))               $errors[] = "Nombre obligatorio.";
    if (empty($client_key))         $errors[] = "Client key obligatorio.";
    elseif (strlen($client_key) !== 32 || !ctype_alnum($client_key)) {
        $errors[] = "Client key debe ser 32 caracteres alfanuméricos.";
    }

    // Unicidad de client_key
    $stmt = $pdo->prepare("SELECT id FROM clients WHERE client_key = ? AND id != ?");
    $stmt->execute([$client_key, $id]);
    if ($stmt->fetch()) {
        $errors[] = "Ese client_key ya existe.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            if ($id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE clients SET name = ?, client_key = ?, orientation = ?, background = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $client_key, $orientation, $background, $id]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO clients (name, client_key, orientation, background, active, playlist_version)
                    VALUES (?, ?, ?, ?, 1, 0)
                ");
                $stmt->execute([$name, $client_key, $orientation, $background]);
                $id = $pdo->lastInsertId();
            }

            // Actualizar relaciones sucursales (solo las permitidas)
            $pdo->prepare("DELETE FROM client_sucursal WHERE client_id = ?")->execute([$id]);

            $sucursales_validas = array_intersect($sucursales_post, $sucursales_ids_permitidas);
            if (!empty($sucursales_validas)) {
                $stmt = $pdo->prepare("INSERT IGNORE INTO client_sucursal (client_id, sucursal_id) VALUES (?, ?)");
                foreach ($sucursales_validas as $suc_id) {
                    $stmt->execute([$id, (int)$suc_id]);
                }
            }

            $pdo->commit();
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Cliente guardado correctamente'];
            header("Location: ?action=clientes");
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Error en base de datos: " . $e->getMessage();
        }
    }
}
?>

<h2 class="mb-4"><?= $id ? 'Editar Cliente' : 'Nuevo Cliente' ?></h2>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <ul class="mb-0">
        <?php foreach ($errors as $err): ?>
            <li><?= htmlspecialchars($err) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<form method="post">
    <input type="hidden" name="id" value="<?= $id ?>">

    <div class="mb-3">
        <label class="form-label fw-bold">Nombre identificador</label>
        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($cliente['name']) ?>" required autofocus>
    </div>

    <div class="mb-3">
        <label class="form-label fw-bold">Client Key (32 caracteres)</label>
        <input type="text" name="client_key" class="form-control font-monospace" 
               value="<?= htmlspecialchars($cliente['client_key']) ?>" 
               maxlength="32" pattern="[A-Za-z0-9]{32}" required <?= $id ? 'readonly' : '' ?>>
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
        <div class="form-text">Solo puedes asignar sucursales que tienes permiso de gestionar.</div>
    </div>

    <div class="d-flex gap-2 justify-content-end">
        <a href="?action=clientes" class="btn btn-secondary">Cancelar</a>
        <button type="submit" class="btn btn-success px-4">Guardar Cliente</button>
    </div>
</form>