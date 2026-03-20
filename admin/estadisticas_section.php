<?php
// ================================================
// estadisticas_section.php - Estadísticas detalladas + export CSV
// ================================================

// Debug en consola para ver qué llega
echo "<script>console.log('Rol: " . addslashes($_SESSION['rol']) . "');</script>";
echo "<script>console.log('Empresas IDs: ', " . json_encode($empresas_ids ?? []) . ");</script>";
echo "<script>console.log('Sucursales IDs: ', " . json_encode($sucursales_ids ?? []) . ");</script>";

// Cargar empresas visibles (solo las que el usuario puede ver)
$empresas = [];
if ($_SESSION['rol'] === 'admin') {
    $empresas = $pdo->query("SELECT id, nombre FROM empresas WHERE activo = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
} elseif (!empty($empresas_ids)) {
    $placeholders = implode(',', array_fill(0, count($empresas_ids), '?'));
    $stmt = $pdo->prepare("SELECT id, nombre FROM empresas WHERE id IN ($placeholders) AND activo = 1 ORDER BY nombre");
    $stmt->execute($empresas_ids);
    $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
echo "<script>console.log('Empresas cargadas para filtro: ', " . json_encode($empresas) . ");</script>";

// Cargar sucursales visibles
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
echo "<script>console.log('Sucursales cargadas para filtro: ', " . json_encode($sucursales) . ");</script>";

// Filtros (GET)
$desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-30 days'));
$hasta = $_GET['hasta'] ?? date('Y-m-d');
$empresa_id = isset($_GET['empresa_id']) ? (int)$_GET['empresa_id'] : 0;
$sucursal_id = isset($_GET['sucursal_id']) ? (int)$_GET['sucursal_id'] : 0;

// Validar filtros (solo permite los que el usuario ve)
if ($_SESSION['rol'] !== 'admin') {
    if ($empresa_id && !in_array($empresa_id, $empresas_ids)) $empresa_id = 0;
    if ($sucursal_id && !in_array($sucursal_id, $sucursales_ids)) $sucursal_id = 0;
}

// Construir WHERE base (filtrado por organizaciones del usuario)
$where = "1=1";
$params = [];
if ($_SESSION['rol'] !== 'admin') {
    if (!empty($sucursales_ids)) {
        $placeholders = implode(',', array_fill(0, count($sucursales_ids), '?'));
        $where .= " AND vs.sucursal_id IN ($placeholders)";
        $params = array_merge($params, $sucursales_ids);
    } else {
        $where .= " AND 1=0"; // Si no tiene sucursales, no muestra nada
    }
}

// Filtros adicionales
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

// Dispositivos activos
$clientes_activos = 0;
if ($_SESSION['rol'] === 'admin') {
    $clientes_activos = $pdo->query("SELECT COUNT(*) FROM clients WHERE last_ping > DATE_SUB(NOW(), INTERVAL 5 MINUTE)")->fetchColumn();
} elseif (!empty($sucursales_ids)) {
    $placeholders = implode(',', array_fill(0, count($sucursales_ids), '?'));
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM clients c 
        INNER JOIN client_sucursal cs ON c.id = cs.client_id 
        WHERE cs.sucursal_id IN ($placeholders) AND c.last_ping > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ");
    $stmt->execute($sucursales_ids);
    $clientes_activos = $stmt->fetchColumn();
}

// Videos nuevos (7 días)
$videos_nuevos = 0;
$videos_nuevos_query = "SELECT COUNT(*) FROM videos v 
                        LEFT JOIN video_sucursal vs ON v.id = vs.video_id 
                        WHERE v.upload_date > DATE_SUB(NOW(), INTERVAL 7 DAY)";
$params_videos_nuevos = [];
if ($_SESSION['rol'] !== 'admin') {
    if (!empty($sucursales_ids)) {
        $placeholders = implode(',', array_fill(0, count($sucursales_ids), '?'));
        $videos_nuevos_query .= " AND vs.sucursal_id IN ($placeholders)";
        $params_videos_nuevos = $sucursales_ids;
    } else {
        $videos_nuevos_query .= " AND 1=0";
    }
}
$stmt = $pdo->prepare($videos_nuevos_query);
$stmt->execute($params_videos_nuevos);
$videos_nuevos = $stmt->fetchColumn();

// Top videos
$top_videos = [];
$top_query = "SELECT v.title, COALESCE(SUM(v.reproducciones), 0) as reproducciones 
              FROM videos v ";
if ($_SESSION['rol'] !== 'admin') {
    $top_query .= "INNER JOIN video_sucursal vs ON v.id = vs.video_id ";
}
$top_query .= "WHERE 1=1 ";
if ($_SESSION['rol'] !== 'admin' && !empty($sucursales_ids)) {
    $placeholders = implode(',', array_fill(0, count($sucursales_ids), '?'));
    $top_query .= "AND vs.sucursal_id IN ($placeholders) ";
}
$top_query .= "GROUP BY v.id ORDER BY reproducciones DESC LIMIT 10";
$stmt = $pdo->prepare($top_query);
if ($_SESSION['rol'] !== 'admin') $stmt->execute($sucursales_ids);
else $stmt->execute();
$top_videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    ob_end_clean();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="estadisticas_' . date('Y-m-d_H-i-s') . '.csv"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    echo "\xEF\xBB\xBF"; // BOM para Excel

    fputcsv($output, ['Métrica', 'Valor']);
    fputcsv($output, ['Total reproducciones', $total_reproducciones ?? 0]);
    fputcsv($output, ['Videos accesibles', $total_videos ?? 0]);
    fputcsv($output, ['Videos nuevos (7 días)', $videos_nuevos ?? 0]);
    fputcsv($output, []);
    fputcsv($output, ['Top Videos', 'Reproducciones']);

    foreach ($top_videos as $v) {
        fputcsv($output, [$v['title'], $v['reproducciones']]);
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
            <input type="date" name="desde" class="form-control" value="<?= htmlspecialchars($desde ?? date('Y-m-d', strtotime('-30 days'))) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Hasta</label>
            <input type="date" name="hasta" class="form-control" value="<?= htmlspecialchars($hasta ?? date('Y-m-d')) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Empresa</label>
            <select name="empresa_id" class="form-select">
                <option value="">Todas</option>
                <?php foreach ($empresas as $e): ?>
                    <option value="<?= $e['id'] ?>" <?= ($empresa_id ?? 0) == $e['id'] ? 'selected' : '' ?>>
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
                    <option value="<?= $s['id'] ?>" <?= ($sucursal_id ?? 0) == $s['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['empresa'] . ' → ' . $s['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="mt-3">
        <button type="submit" class="btn btn-primary">Filtrar</button>
        <a href="?action=estadisticas&export=csv&desde=<?= urlencode($desde ?? date('Y-m-d', strtotime('-30 days'))) ?>&hasta=<?= urlencode($hasta ?? date('Y-m-d')) ?>&empresa_id=<?= $empresa_id ?? '' ?>&sucursal_id=<?= $sucursal_id ?? '' ?>" 
           class="btn btn-success ms-2">Exportar a CSV</a>
    </div>
</form>

<!-- Métricas principales -->
<div class="row g-4">
    <div class="col-md-4">
        <div class="card border-success shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title">Total Reproducciones</h5>
                <h2 class="card-text"><?= number_format($total_reproducciones ?? 0) ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-primary shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title">Videos Accesibles</h5>
                <h2 class="card-text"><?= number_format($total_videos ?? 0) ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-info shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title">Dispositivos Activos</h5>
                <h2 class="card-text"><?= number_format($clientes_activos ?? 0) ?></h2>
            </div>
        </div>
    </div>
</div>

<!-- Gráficos -->
<div class="row g-4 mt-4">
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
        new Chart(ctxEstados.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Activos', 'Inactivos', 'Offline'],
                datasets: [{
                    data: [<?= $clientes_activos ?? 0 ?>, <?= ($total_clientes ?? 0) - ($clientes_activos ?? 0) - ($clientes_offline ?? 0) ?>, <?= $clientes_offline ?? 0 ?>],
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
    }
</script>