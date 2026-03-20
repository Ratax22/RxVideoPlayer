<?php
// ================================================
// dashboard_section.php - Resumen personalizado
// ================================================

// Empresas y sucursales accesibles para el usuario actual
$empresas_ids = getEmpresasAcceso($pdo, $_SESSION['usuario_id'], $_SESSION['rol']);
$sucursales_ids = getSucursalesAcceso($pdo, $_SESSION['usuario_id'], $_SESSION['rol']);

// ================================================
// Métricas filtradas por permisos
// ================================================

// Total clientes accesibles
if ($_SESSION['rol'] === 'admin') {
    $total_clientes = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
} else {
    if (empty($sucursales_ids)) {
        $total_clientes = 0;
    } else {
        $placeholders = implode(',', array_fill(0, count($sucursales_ids), '?'));
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT c.id) FROM clients c INNER JOIN client_sucursal cs ON c.id = cs.client_id WHERE cs.sucursal_id IN ($placeholders)");
        $stmt->execute($sucursales_ids);
        $total_clientes = $stmt->fetchColumn();
    }
}

// Total videos accesibles
if ($_SESSION['rol'] === 'admin') {
    $total_videos = $pdo->query("SELECT COUNT(*) FROM videos")->fetchColumn();
} else {
    if (empty($sucursales_ids)) {
        $total_videos = 0;
    } else {
        $placeholders = implode(',', array_fill(0, count($sucursales_ids), '?'));
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT v.id) FROM videos v INNER JOIN video_sucursal vs ON v.id = vs.video_id WHERE vs.sucursal_id IN ($placeholders)");
        $stmt->execute($sucursales_ids);
        $total_videos = $stmt->fetchColumn();
    }
}

// Videos más reproducidos (top 5) - asumiendo que agregaste campo reproducciones en videos
$top_videos = [];
$stmt = $pdo->prepare("
    SELECT v.id, v.title, v.reproducciones 
    FROM videos v
    " . ($_SESSION['rol'] !== 'admin' ? "INNER JOIN video_sucursal vs ON v.id = vs.video_id" : "") . "
    WHERE " . ($_SESSION['rol'] !== 'admin' ? "vs.sucursal_id IN (" . implode(',', array_fill(0, count($sucursales_ids), '?')) . ")" : "1=1") . "
    ORDER BY v.reproducciones DESC 
    LIMIT 5
");
if ($_SESSION['rol'] !== 'admin') $stmt->execute($sucursales_ids);
else $stmt->execute();
$top_videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Clientes con playlist desactualizada o sin videos
$clientes_sin_playlist = $pdo->query("SELECT COUNT(*) FROM clients WHERE playlist_version = 0")->fetchColumn();

// Alertas: clientes offline > 1 hora
$alertas_offline = [];
$stmt = $pdo->prepare("
    SELECT c.name, c.last_ping 
    FROM clients c
    " . ($_SESSION['rol'] !== 'admin' ? "INNER JOIN client_sucursal cs ON c.id = cs.client_id" : "") . "
    WHERE c.last_ping < DATE_SUB(NOW(), INTERVAL 1 HOUR) 
    " . ($_SESSION['rol'] !== 'admin' ? "AND cs.sucursal_id IN (" . implode(',', array_fill(0, count($sucursales_ids), '?')) . ")" : "") . "
    ORDER BY c.last_ping DESC 
    LIMIT 5
");
if ($_SESSION['rol'] !== 'admin') $stmt->execute($sucursales_ids);
else $stmt->execute();
$alertas_offline = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h1 class="mb-4">Dashboard - Bienvenido, <?= htmlspecialchars($_SESSION['nombre']) ?></h1>

<div class="row g-4">

    <!-- Card: Dispositivos -->
    <div class="col-md-6 col-lg-3">
        <div class="card border-primary h-100 shadow-sm">
            <div class="card-body">
                <h5 class="card-title text-primary">Dispositivos</h5>
                <h2 class="card-text"><?= number_format($total_clientes) ?></h2>
                <p class="text-muted small">
                    Sin playlist: <strong><?= $clientes_sin_playlist ?></strong>
                </p>
            </div>
        </div>
    </div>

    <!-- Card: Videos -->
    <div class="col-md-6 col-lg-3">
        <div class="card border-success h-100 shadow-sm">
            <div class="card-body">
                <h5 class="card-title text-success">Videos</h5>
                <h2 class="card-text"><?= number_format($total_videos) ?></h2>
                <p class="text-muted small">
                    Total accesibles para ti
                </p>
            </div>
        </div>
    </div>

    <!-- Card: Organizaciones -->
    <div class="col-md-6 col-lg-3">
        <div class="card border-info h-100 shadow-sm">
            <div class="card-body">
                <h5 class="card-title text-info">Organización</h5>
                <h2 class="card-text"><?= number_format(count($empresas_ids)) ?></h2>
                <p class="text-muted small">
                    Sucursales: <strong><?= number_format(count($sucursales_ids)) ?></strong>
                </p>
            </div>
        </div>
    </div>

    <!-- Card: Alertas -->
    <div class="col-md-6 col-lg-3">
        <div class="card border-danger h-100 shadow-sm">
            <div class="card-body">
                <h5 class="card-title text-danger">Alertas</h5>
                <h2 class="card-text"><?= count($alertas_offline) ?></h2>
                <p class="text-muted small">
                    Dispositivos offline >1h
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Gráfico: Top 5 videos más reproducidos -->
<?php if (!empty($top_videos)): ?>
<div class="card mt-4 shadow-sm">
    <div class="card-header bg-success text-white">
        <h5 class="mb-0">Top 5 videos más reproducidos</h5>
    </div>
    <div class="card-body">
        <canvas id="topVideosChart" height="200"></canvas>
    </div>
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

<!-- Chart.js para top videos -->
<?php if (!empty($top_videos)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('topVideosChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: [<?php foreach ($top_videos as $v) echo "'" . addslashes(substr($v['title'], 0, 20)) . "', "; ?>],
            datasets: [{
                label: 'Reproducciones',
                data: [<?php foreach ($top_videos as $v) echo $v['reproducciones'] . ', '; ?>],
                backgroundColor: '#28a745',
                borderColor: '#1e7e34',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: { beginAtZero: true }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });
</script>
<?php endif; ?>