<?php
// ================================================
// estadisticas_section.php
// Estadísticas detalladas + export a CSV
// ================================================

// Filtros (por GET o POST)
$desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-30 days'));
$hasta = $_GET['hasta'] ?? date('Y-m-d');
$empresa_id = isset($_GET['empresa_id']) ? (int)$_GET['empresa_id'] : 0;
$sucursal_id = isset($_GET['sucursal_id']) ? (int)$_GET['sucursal_id'] : 0;

// Empresas y sucursales accesibles
$empresas_ids = getEmpresasAcceso($pdo, $_SESSION['usuario_id'], $_SESSION['rol']);
$sucursales_ids = getSucursalesAcceso($pdo, $_SESSION['usuario_id'], $_SESSION['rol']);

// Validar filtros según permisos
if ($_SESSION['rol'] !== 'admin') {
    if ($empresa_id && !in_array($empresa_id, $empresas_ids)) $empresa_id = 0;
    if ($sucursal_id && !in_array($sucursal_id, $sucursales_ids)) $sucursal_id = 0;
}

// Construir WHERE para consultas
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
    $where .= " AND vs.empresa_id = ?";
    $params[] = $empresa_id;
}
if ($sucursal_id) {
    $where .= " AND vs.sucursal_id = ?";
    $params[] = $sucursal_id;
}

// Total reproducciones
$stmt = $pdo->prepare("
    SELECT SUM(v.reproducciones) 
    FROM videos v
    INNER JOIN video_sucursal vs ON v.id = vs.video_id
    WHERE $where
");
$stmt->execute($params);
$total_reproducciones = $stmt->fetchColumn() ?: 0;

// Top 10 videos más reproducidos
$stmt = $pdo->prepare("
    SELECT v.title, v.reproducciones 
    FROM videos v
    INNER JOIN video_sucursal vs ON v.id = vs.video_id
    WHERE $where
    GROUP BY v.id
    ORDER BY v.reproducciones DESC 
    LIMIT 10
");
$stmt->execute($params);
$top_videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Reproducciones por día (últimos 30 días)
$repro_por_dia = [];
$stmt = $pdo->prepare("
    SELECT DATE(v.upload_date) as fecha, SUM(v.reproducciones) as total 
    FROM videos v
    INNER JOIN video_sucursal vs ON v.id = vs.video_id
    WHERE $where AND v.upload_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY fecha
    ORDER BY fecha ASC
");
$stmt->execute($params);
while ($row = $stmt->fetch()) {
    $repro_por_dia[$row['fecha']] = $row['total'];
}

// Exportar CSV si se pidió
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=estadisticas_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Fecha', 'Reproducciones']);
    foreach ($repro_por_dia as $fecha => $total) {
        fputcsv($output, [$fecha, $total]);
    }
    fclose($output);
    exit;
}
?>

<h1 class="mb-4">Estadísticas</h1>

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
    <!-- Torta estados dispositivos -->
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

    <!-- Top videos -->
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Top 10 videos más reproducidos</h5>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($top_videos)): ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($top_videos as $index => $v): ?>
                        <li class="list-group-item <?= $index === 0 ? 'bg-success-subtle fw-bold' : '' ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
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
    // Torta estados
    const ctxEstados = document.getElementById('estadosChart').getContext('2d');
    new Chart(ctxEstados, {
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
</script>