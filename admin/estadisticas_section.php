<?php
// ================================================
// estadisticas_section.php
// Estadísticas + export CSV puro
// ================================================

// Iniciar buffer para limpiar cualquier salida accidental
ob_start();

// Exportar CSV (primero, antes de cualquier HTML)
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Limpiar buffer
    ob_end_clean();

    // Headers estrictos para CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="estadisticas_' . date('Y-m-d_H-i-s') . '.csv"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    // BOM para que Excel reconozca UTF-8 correctamente (opcional pero recomendado)
    echo "\xEF\xBB\xBF";

    // Cabeceras CSV
    fputcsv($output, ['Métrica', 'Valor']);

    // Métricas (ajustá según tus variables reales)
    fputcsv($output, ['Total reproducciones', $total_reproducciones ?? 0]);
    fputcsv($output, ['Videos accesibles', $total_videos ?? 0]);
    fputcsv($output, ['Videos nuevos (7 días)', $videos_nuevos ?? 0]);
    fputcsv($output, []);
    fputcsv($output, ['Top Videos', 'Reproducciones']);

    // Top videos
    foreach ($top_videos ?? [] as $v) {
        fputcsv($output, [$v['title'], $v['reproducciones']]);
    }

    fclose($output);
    exit; // ¡Corta todo inmediatamente!
}

// Si no es export → limpiar buffer y continuar con HTML
ob_end_clean();
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

<!-- Resto del contenido: cards, gráficos, toast, alertas... -->
<div class="row g-4">
    <!-- Tus cards de métricas aquí -->
    <div class="col-md-4">
        <div class="card border-success shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title">Total Reproducciones</h5>
                <h2 class="card-text"><?= number_format($total_reproducciones ?? 0) ?></h2>
            </div>
        </div>
    </div>
    <!-- ... resto de cards ... -->
</div>

<!-- Gráficos -->
<div class="row g-4 mt-4">
    <!-- Torta y listado -->
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
    // Tu código de Chart.js aquí
</script>