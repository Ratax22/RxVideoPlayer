<?php
// ================================================
// estadisticas_section.php
// Estadísticas detalladas + export a CSV puro
// ================================================

// Exportar CSV (se ejecuta primero, antes de cualquier salida)
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Headers para forzar descarga CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="estadisticas_' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    // Cabeceras CSV
    fputcsv($output, ['Métrica', 'Valor']);

    // Ejemplos de métricas (agregá las que quieras)
    fputcsv($output, ['Total reproducciones', $total_reproducciones ?? 0]);
    fputcsv($output, ['Videos accesibles', $total_videos ?? 0]);
    fputcsv($output, ['Videos nuevos (7 días)', $videos_nuevos ?? 0]);
    fputcsv($output, []);

    // Top videos
    fputcsv($output, ['Top Videos', 'Reproducciones']);
    foreach ($top_videos ?? [] as $v) {
        fputcsv($output, [$v['title'], $v['reproducciones']]);
    }

    fclose($output);
    exit; // ¡Corta todo! No se envía HTML
}

// Si no es export → HTML normal
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
                <?php foreach ($empresas ?? [] as $e): ?>
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
                <?php foreach ($sucursales ?? [] as $s): ?>
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