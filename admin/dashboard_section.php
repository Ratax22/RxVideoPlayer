<?php
// ================================================
// dashboard_section.php - Dashboard principal personalizado
// ================================================

// Empresas y sucursales accesibles
$empresas_ids = getEmpresasAcceso($pdo, $_SESSION['usuario_id'], $_SESSION['rol']);
$sucursales_ids = getSucursalesAcceso($pdo, $_SESSION['usuario_id'], $_SESSION['rol']);

// Total clientes accesibles
if ($_SESSION['rol'] === 'admin') {
    $total_clientes = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
} else {
    $total_clientes = 0;
    if (!empty($sucursales_ids)) {
        $placeholders = implode(',', array_fill(0, count($sucursales_ids), '?'));
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT c.id) 
            FROM clients c 
            INNER JOIN client_sucursal cs ON c.id = cs.client_id 
            WHERE cs.sucursal_id IN ($placeholders)
        ");
        $stmt->execute($sucursales_ids);
        $total_clientes = $stmt->fetchColumn();
    }
}

// Total videos accesibles
if ($_SESSION['rol'] === 'admin') {
    $total_videos = $pdo->query("SELECT COUNT(*) FROM videos")->fetchColumn();
} else {
    $total_videos = 0;
    if (!empty($sucursales_ids)) {
        $placeholders = implode(',', array_fill(0, count($sucursales_ids), '?'));
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT v.id) 
            FROM videos v 
            INNER JOIN video_sucursal vs ON v.id = vs.video_id 
            WHERE vs.sucursal_id IN ($placeholders)
        ");
        $stmt->execute($sucursales_ids);
        $total_videos = $stmt->fetchColumn();
    }
}

// Clientes activos / offline
$clientes_activos = $pdo->query("
    SELECT COUNT(*) FROM clients 
    WHERE last_ping > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
")->fetchColumn();

$clientes_offline = $pdo->query("
    SELECT COUNT(*) FROM clients 
    WHERE last_ping < DATE_SUB(NOW(), INTERVAL 1 HOUR) OR last_ping IS NULL
")->fetchColumn();

// Top 5 videos más reproducidos (filtrado)
$top_videos = [];
if ($_SESSION['rol'] === 'admin') {
    $stmt = $pdo->prepare("
        SELECT id, title, reproducciones 
        FROM videos 
        ORDER BY reproducciones DESC 
        LIMIT 5
    ");
    $stmt->execute();
} else {
    if (!empty($sucursales_ids)) {
        $placeholders = implode(',', array_fill(0, count($sucursales_ids), '?'));
        $stmt = $pdo->prepare("
            SELECT v.id, v.title, v.reproducciones 
            FROM videos v
            INNER JOIN video_sucursal vs ON v.id = vs.video_id
            WHERE vs.sucursal_id IN ($placeholders)
            GROUP BY v.id
            ORDER BY v.reproducciones DESC 
            LIMIT 5
        ");
        $stmt->execute($sucursales_ids);
    }
}
$top_videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Alertas offline (filtradas)
$alertas_offline = [];
if ($_SESSION['rol'] === 'admin') {
    $stmt = $pdo->prepare("
        SELECT name, last_ping 
        FROM clients 
        WHERE last_ping < DATE_SUB(NOW(), INTERVAL 1 HOUR) 
        ORDER BY last_ping DESC 
        LIMIT 5
    ");
    $stmt->execute();
} else {
    if (!empty($sucursales_ids)) {
        $placeholders = implode(',', array_fill(0, count($sucursales_ids), '?'));
        $stmt = $pdo->prepare("
            SELECT c.name, c.last_ping 
            FROM clients c
            INNER JOIN client_sucursal cs ON c.id = cs.client_id
            WHERE c.last_ping < DATE_SUB(NOW(), INTERVAL 1 HOUR) 
            AND cs.sucursal_id IN ($placeholders)
            ORDER BY c.last_ping DESC 
            LIMIT 5
        ");
        $stmt->execute($sucursales_ids);
    }
}
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

<!-- Toast de videos nuevos -->
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
    <!-- Torta: Estados de dispositivos -->
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

    <!-- Listado vertical: Top videos más reproducidos -->
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Top videos más reproducidos</h5>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($top_videos)): ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($top_videos as $index => $v): 
                        $highlight = $index === 0 ? 'bg-success-subtle fw-bold' : ($index === 1 ? 'bg-info-subtle' : '');
                    ?>
                        <li class="list-group-item <?= $highlight ?>">
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

<!-- Chart.js - Torta estados -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
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