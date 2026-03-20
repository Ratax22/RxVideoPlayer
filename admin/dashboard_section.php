<?php
// dashboard_section.php - Resumen rápido

// Empresas y sucursales accesibles
$empresas_ids = getEmpresasAcceso($pdo, $_SESSION['usuario_id'], $_SESSION['rol']);
$sucursales_ids = getSucursalesAcceso($pdo, $_SESSION['usuario_id'], $_SESSION['rol']);

// Total clientes accesibles
$total_clientes = 0;
if ($_SESSION['rol'] === 'admin') {
    $total_clientes = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
} elseif (!empty($sucursales_ids)) {
    $placeholders = implode(',', array_fill(0, count($sucursales_ids), '?'));
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT c.id) FROM clients c INNER JOIN client_sucursal cs ON c.id = cs.client_id WHERE cs.sucursal_id IN ($placeholders)");
    $stmt->execute($sucursales_ids);
    $total_clientes = $stmt->fetchColumn();
}

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
$clientes_activos = $pdo->query("SELECT COUNT(*) FROM clients WHERE last_ping > DATE_SUB(NOW(), INTERVAL 5 MINUTE)")->fetchColumn();
$clientes_offline = $pdo->query("SELECT COUNT(*) FROM clients WHERE last_ping < DATE_SUB(NOW(), INTERVAL 1 HOUR) OR last_ping IS NULL")->fetchColumn();

// Videos nuevos (7 días)
$videos_nuevos = 0;
if ($_SESSION['rol'] === 'admin') {
    $videos_nuevos = $pdo->query("SELECT COUNT(*) FROM videos WHERE upload_date > DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
} elseif (!empty($sucursales_ids)) {
    $placeholders = implode(',', array_fill(0, count($sucursales_ids), '?'));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM videos v INNER JOIN video_sucursal vs ON v.id = vs.video_id WHERE vs.sucursal_id IN ($placeholders) AND v.upload_date > DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stmt->execute($sucursales_ids);
    $videos_nuevos = $stmt->fetchColumn();
}

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
$top_query .= "GROUP BY v.id ORDER BY reproducciones DESC LIMIT 5";
$stmt = $pdo->prepare($top_query);
if ($_SESSION['rol'] !== 'admin') $stmt->execute($sucursales_ids);
else $stmt->execute();
$top_videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Alertas offline
$alertas_offline = [];
$alert_query = "SELECT name, last_ping FROM clients WHERE last_ping < DATE_SUB(NOW(), INTERVAL 1 HOUR) ";
if ($_SESSION['rol'] !== 'admin' && !empty($sucursales_ids)) {
    $placeholders = implode(',', array_fill(0, count($sucursales_ids), '?'));
    $alert_query .= "AND id IN (SELECT client_id FROM client_sucursal WHERE sucursal_id IN ($placeholders)) ";
}
$alert_query .= "ORDER BY last_ping DESC LIMIT 5";
$stmt = $pdo->prepare($alert_query);
if ($_SESSION['rol'] !== 'admin') $stmt->execute($sucursales_ids);
else $stmt->execute();
$alertas_offline = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h1 class="mb-4">Dashboard</h1>

<div class="row g-4 mb-4">
    <div class="col-md-6 col-lg-3">
        <div class="card border-primary h-100 shadow-sm">
            <div class="card-body">
                <h5 class="card-title text-primary">Dispositivos</h5>
                <h2 class="card-text"><?= number_format($total_clientes) ?></h2>
                <p class="text-muted small">
                    Activos: <strong><?= number_format($clientes_activos) ?></strong><br>
                    Offline: <strong><?= number_format($clientes_offline) ?></strong>
                </p>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-lg-3">
        <div class="card border-success h-100 shadow-sm">
            <div class="card-body">
                <h5 class="card-title text-success">Videos</h5>
                <h2 class="card-text"><?= number_format($total_videos) ?></h2>
                <p class="text-muted small">
                    Accesibles para ti
                </p>
            </div>
        </div>
    </div>

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

    <div class="col-md-6 col-lg-3">
        <div class="card border-danger h-100 shadow-sm">
            <div class="card-body">
                <h5 class="card-title text-danger">Alertas</h5>
                <h2 class="card-text"><?= count($alertas_offline) ?></h2>
                <p class="text-muted small">
                    Offline >1h
                </p>
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

<!-- Chart.js torta -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctxEstados = document.getElementById('estadosChart');
    if (ctxEstados) {
        new Chart(ctxEstados.getContext('2d'), {
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
    }
</script>