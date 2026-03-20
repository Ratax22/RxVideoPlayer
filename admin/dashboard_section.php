<?php
// ================================================
// dashboard_section.php - Dashboard principal personalizado
// ================================================

// Debug: mostrar en consola del navegador
echo "<script>console.log('Rol actual: " . addslashes($_SESSION['rol']) . "');</script>";
echo "<script>console.log('Empresas accesibles: ', " . json_encode($empresas_ids ?? []) . ");</script>";
echo "<script>console.log('Sucursales accesibles: ', " . json_encode($sucursales_ids ?? []) . ");</script>";

// Empresas y sucursales accesibles
$empresas_ids = getEmpresasAcceso($pdo, $_SESSION['usuario_id'], $_SESSION['rol']);
$sucursales_ids = getSucursalesAcceso($pdo, $_SESSION['usuario_id'], $_SESSION['rol']);

// Cargar empresas para filtro
$empresas = [];
if ($_SESSION['rol'] === 'admin') {
    $empresas = $pdo->query("SELECT id, nombre FROM empresas WHERE activo = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
} elseif (!empty($empresas_ids)) {
    $placeholders = implode(',', array_fill(0, count($empresas_ids), '?'));
    $stmt = $pdo->prepare("SELECT id, nombre FROM empresas WHERE id IN ($placeholders) AND activo = 1 ORDER BY nombre");
    $stmt->execute($empresas_ids);
    $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
echo "<script>console.log('Empresas para filtro: ', " . json_encode($empresas) . ");</script>";

// Cargar sucursales para filtro
$sucursales = [];
if ($_SESSION['rol'] === 'admin') {
    $sucursales = $pdo->query("
        SELECT s.id, s.nombre, e.nombre AS empresa 
        FROM sucursales s 
        INNER JOIN empresas e ON s.empresa_id = e.id 
        WHERE s.activo = 1 
        ORDER BY e.nombre, s.nombre
    ")->fetchAll(PDO::FETCH_ASSOC);
} elseif (!empty($sucursales_ids)) {
    $placeholders = implode(',', array_fill(0, count($sucursales_ids), '?'));
    $stmt = $pdo->prepare("
        SELECT s.id, s.nombre, e.nombre AS empresa 
        FROM sucursales s 
        INNER JOIN empresas e ON s.empresa_id = e.id 
        WHERE s.id IN ($placeholders) AND s.activo = 1 
        ORDER BY e.nombre, s.nombre
    ");
    $stmt->execute($sucursales_ids);
    $sucursales = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
echo "<script>console.log('Sucursales para filtro: ', " . json_encode($sucursales) . ");</script>";

// Filtros
$desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-30 days'));
$hasta = $_GET['hasta'] ?? date('Y-m-d');
$empresa_id = isset($_GET['empresa_id']) ? (int)$_GET['empresa_id'] : 0;
$sucursal_id = isset($_GET['sucursal_id']) ? (int)$_GET['sucursal_id'] : 0;

// Construir WHERE
$where = "1=1";
$params = [];
if ($desde) {
    $where .= " AND DATE(v.upload_date) >= ?";
    $params[] = $desde;
}
if ($hasta) {
    $where .= " AND DATE(v.upload_date) <= ?";
    $params[] = $hasta;
}
if ($empresa_id) {
    $where .= " AND e.id = ?";
    $params[] = $empresa_id;
}
if ($sucursal_id) {
    $where .= " AND s.id = ?";
    $params[] = $sucursal_id;
}

// Total reproducciones
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(v.reproducciones), 0) 
    FROM videos v
    LEFT JOIN video_sucursal vs ON v.id = vs.video_id
    LEFT JOIN sucursales s ON vs.sucursal_id = s.id
    LEFT JOIN empresas e ON s.empresa_id = e.id
    WHERE $where
");
$stmt->execute($params);
$total_reproducciones = $stmt->fetchColumn();

// Total videos accesibles
$total_videos = 0;
if ($_SESSION['rol'] === 'admin') {
    $total_videos = $pdo->query("SELECT COUNT(*) FROM videos")->fetchColumn();
} elseif (!empty($sucursales_ids)) {
    $placeholders = implode(',', array_fill(0, count($sucursales_ids), '?'));
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT v.id) FROM videos v INNER JOIN video_sucursal vs ON v.id = vs.video_id WHERE vs.sucursal_id IN ($placeholders)");
    $stmt->execute($sucursales_ids);
    $total_videos = $stmt->fetchColumn();
}

// Clientes activos / offline
$clientes_activos = 0;
$clientes_offline = 0;
if ($_SESSION['rol'] === 'admin') {
    $clientes_activos = $pdo->query("SELECT COUNT(*) FROM clients WHERE last_ping > DATE_SUB(NOW(), INTERVAL 5 MINUTE)")->fetchColumn();
    $clientes_offline = $pdo->query("SELECT COUNT(*) FROM clients WHERE last_ping < DATE_SUB(NOW(), INTERVAL 1 HOUR) OR last_ping IS NULL")->fetchColumn();
} elseif (!empty($sucursales_ids)) {
    $placeholders = implode(',', array_fill(0, count($sucursales_ids), '?'));
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM clients c 
        INNER JOIN client_sucursal cs ON c.id = cs.client_id 
        WHERE cs.sucursal_id IN ($placeholders) AND c.last_ping > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ");
    $stmt->execute($sucursales_ids);
    $clientes_activos = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM clients c 
        INNER JOIN client_sucursal cs ON c.id = cs.client_id 
        WHERE cs.sucursal_id IN ($placeholders) 
        AND (c.last_ping < DATE_SUB(NOW(), INTERVAL 1 HOUR) OR c.last_ping IS NULL)
    ");
    $stmt->execute($sucursales_ids);
    $clientes_offline = $stmt->fetchColumn();
}

// Top videos
$top_videos = [];
if ($_SESSION['rol'] === 'admin') {
    $top_videos = $pdo->query("SELECT title, reproducciones FROM videos ORDER BY reproducciones DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
} elseif (!empty($sucursales_ids)) {
    $placeholders = implode(',', array_fill(0, count($sucursales_ids), '?'));
    $stmt = $pdo->prepare("
        SELECT v.title, v.reproducciones 
        FROM videos v 
        INNER JOIN video_sucursal vs ON v.id = vs.video_id 
        WHERE vs.sucursal_id IN ($placeholders) 
        ORDER BY v.reproducciones DESC 
        LIMIT 5
    ");
    $stmt->execute($sucursales_ids);
    $top_videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Videos nuevos
$videos_nuevos = 0;
if ($_SESSION['rol'] === 'admin') {
    $videos_nuevos = $pdo->query("SELECT COUNT(*) FROM videos WHERE upload_date > DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
} elseif (!empty($sucursales_ids)) {
    $placeholders = implode(',', array_fill(0, count($sucursales_ids), '?'));
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM videos v 
        INNER JOIN video_sucursal vs ON v.id = vs.video_id 
        WHERE vs.sucursal_id IN ($placeholders) 
        AND v.upload_date > DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute($sucursales_ids);
    $videos_nuevos = $stmt->fetchColumn();
}
?>

<h1 class="mb-4">Dashboard</h1>

<!-- Filtros -->
<form method="get" class="mb-4">
    <input type="hidden" name="action" value="estadisticas">
    <div class="row g-3">
        <div class="col-md-3">
            <label class="form-label">Desde</label>
            <input type="date" name="desde" class="form-control" value="<?= htmlspecialchars($desde) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Hasta</label>
            <input type="date" name="hasta" class="form-control" value="<?= htmlspecialchars($hasta) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Empresa</label>
            <select name="empresa_id" class="form-select">
                <option value="">Todas</option>
                <?php foreach ($empresas as $e): ?>
                    <option value="<?= $e['id'] ?>" <?= $empresa_id == $e['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($e['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Sucursal</label>
            <select name="sucursal_id" class="form-select">
                <option value="">Todas</option>
                <?php foreach ($sucursales as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $sucursal_id == $s['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['empresa'] . ' → ' . $s['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="mt-3">
        <button type="submit" class="btn btn-primary">Filtrar</button>
        <a href="?action=estadisticas&export=csv&desde=<?= urlencode($desde) ?>&hasta=<?= urlencode($hasta) ?>&empresa_id=<?= $empresa_id ?>&sucursal_id=<?= $sucursal_id ?>" 
           class="btn btn-success ms-2">Exportar a CSV</a>
    </div>
</form>

<div class="row g-4">
    <!-- Métricas principales -->
    <div class="col-md-4">
        <div class="card border-success shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title">Total Reproducciones</h5>
                <h2 class="card-text"><?= number_format($total_reproducciones) ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-primary shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title">Videos Accesibles</h5>
                <h2 class="card-text"><?= number_format($total_videos) ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-info shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title">Dispositivos Activos</h5>
                <h2 class="card-text"><?= number_format($clientes_activos) ?></h2>
            </div>
        </div>
    </div>
</div>

<!-- Gráficos -->
<div class="row g-4 mt-4">
    <!-- Torta estados -->
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Estados de dispositivos</h5>
            </div>
            <div class="card-body">
                <canvas id="estadosChart" height="250"></canvas>
            </div>
        </div>
    </div>

    <!-- Listado top videos -->
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Top videos más reproducidos</h5>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($top_videos)): ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($top_videos as $index => $v): ?>
                        <li class="list-group-item <?= $index === 0 ? 'bg-success-subtle fw-bold' : '' ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <span class="badge bg-dark rounded-pill me-3" style="width:32px;">
                                        <?= $index + 1 ?>
                                    </span>
                                    <?= htmlspecialchars($v['title']) ?>
                                </div>
                                <span class="badge bg-success rounded-pill">
                                    <?= number_format($v['reproducciones']) ?>
                                </span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php else: ?>
                <p class="text-muted text-center py-4">Aún no hay reproducciones registradas.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Toast videos nuevos -->
<?php if ($videos_nuevos > 0): ?>
<div class="alert alert-info alert-dismissible fade show mt-4" role="alert">
    <i class="bi bi-stars-fill me-2 text-warning"></i>
    <strong>¡<?= $videos_nuevos ?> video<?= $videos_nuevos > 1 ? 's' : '' ?> nuevo<?= $videos_nuevos > 1 ? 's' : '' ?> disponible<?= $videos_nuevos > 1 ? 's' : '' ?>!</strong>
    <br>
    Hay contenido reciente que aún no viste.
    <a href="?action=videos" class="alert-link fw-bold ms-2">Ver videos ahora →</a>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- Alertas offline -->
<?php if (!empty($alertas_offline)): ?>
<div class="alert alert-danger mt-4">
    <h5>Dispositivos offline (>1 hora)</h5>
    <ul class="mb-0">
        <?php foreach ($alertas_offline as $alerta): ?>
            <li>
                <?= htmlspecialchars($alerta['name']) ?> 
                - Último ping: <?= $alerta['last_ping'] ? date('d/m/Y H:i', strtotime($alerta['last_ping'])) : 'Nunca' ?>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctxEstados = document.getElementById('estadosChart');
    if (ctxEstados) {
        const estadosChart = new Chart(ctxEstados.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Activos', 'Inactivos', 'Offline'],
                datasets: [{
                    data: [<?= $clientes_activos ?>, <?= $total_clientes - $clientes_activos - $clientes_offline ?>, <?= $clientes_offline ?>],
                    backgroundColor: ['#28a745', '#ffc107', '#dc3545'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'top' },
                    title: { display: true, text: 'Estados actuales' }
                }
            }
        });
        console.log('Gráfico torta cargado con éxito');
    } else {
        console.error('Canvas #estadosChart no encontrado');
    }
</script>