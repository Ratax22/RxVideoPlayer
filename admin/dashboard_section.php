<?php
// ================================================
// dashboard_section.php - Resumen principal del panel
// ================================================

// Métricas generales
$total_clientes = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
$total_videos   = $pdo->query("SELECT COUNT(*) FROM videos")->fetchColumn();
$total_empresas = $pdo->query("SELECT COUNT(*) FROM empresas")->fetchColumn();
$total_sucursales = $pdo->query("SELECT COUNT(*) FROM sucursales")->fetchColumn();

// Clientes por estado (aproximado)
$clientes_activos = $pdo->query("
    SELECT COUNT(*) FROM clients 
    WHERE last_ping > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
")->fetchColumn();

$clientes_offline = $pdo->query("
    SELECT COUNT(*) FROM clients 
    WHERE last_ping < DATE_SUB(NOW(), INTERVAL 1 HOUR) OR last_ping IS NULL
")->fetchColumn();

// Últimos 5 clientes actualizados (playlist_version > 0)
$stmt = $pdo->prepare("
    SELECT c.name, c.playlist_version, c.last_ping 
    FROM clients c 
    ORDER BY c.playlist_version DESC, c.last_ping DESC 
    LIMIT 5
");
$stmt->execute();
$ultimos_clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Videos nuevos (últimos 7 días)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM videos WHERE upload_date > DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stmt->execute();
$videos_nuevos = $stmt->fetchColumn();

// Alertas críticas (clientes offline > 1 hora)
$stmt = $pdo->prepare("
    SELECT name, last_ping 
    FROM clients 
    WHERE last_ping < DATE_SUB(NOW(), INTERVAL 1 HOUR) AND last_ping IS NOT NULL 
    ORDER BY last_ping DESC 
    LIMIT 5
");
$stmt->execute();
$alertas_offline = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h1 class="mb-4">Dashboard</h1>

<div class="row g-4">

    <!-- Card: Clientes -->
    <div class="col-md-6 col-lg-3">
        <div class="card border-primary h-100 shadow-sm">
            <div class="card-body">
                <h5 class="card-title text-primary">Dispositivos / Clientes</h5>
                <h2 class="card-text"><?= number_format($total_clientes) ?></h2>
                <p class="text-muted small">
                    Activos: <strong><?= $clientes_activos ?></strong><br>
                    Offline: <strong><?= $clientes_offline ?></strong>
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
                    Nuevos últimos 7 días: <strong><?= $videos_nuevos ?></strong>
                </p>
            </div>
        </div>
    </div>

    <!-- Card: Empresas / Sucursales -->
    <div class="col-md-6 col-lg-3">
        <div class="card border-info h-100 shadow-sm">
            <div class="card-body">
                <h5 class="card-title text-info">Organización</h5>
                <h2 class="card-text"><?= number_format($total_empresas) ?></h2>
                <p class="text-muted small">
                    Sucursales: <strong><?= number_format($total_sucursales) ?></strong>
                </p>
            </div>
        </div>
    </div>

    <!-- Card: Última actividad -->
    <div class="col-md-6 col-lg-3">
        <div class="card border-warning h-100 shadow-sm">
            <div class="card-body">
                <h5 class="card-title text-warning">Última actividad</h5>
                <p class="card-text small">
                    <?php if (!empty($ultimos_clientes)): ?>
                        Última playlist actualizada:<br>
                        <strong><?= htmlspecialchars($ultimos_clientes[0]['name']) ?></strong> 
                        (v<?= $ultimos_clientes[0]['playlist_version'] ?>)
                    <?php else: ?>
                        Sin actividad reciente
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Alertas críticas -->
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

<!-- Gráfico simple: Estados de clientes (Chart.js) -->
<div class="card mt-4 shadow-sm">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0">Estados de dispositivos</h5>
    </div>
    <div class="card-body">
        <canvas id="clientesChart" height="200"></canvas>
    </div>
</div>

<!-- Scripts Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('clientesChart').getContext('2d');
    new Chart(ctx, {
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