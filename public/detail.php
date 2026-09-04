<?php
$config = require __DIR__ . '/../config/database.php';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: index.php');
    exit;
}

try {
    $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
    $pdo = new PDO($dsn, $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $stmt = $pdo->prepare("SELECT * FROM proyectos WHERE id = :id AND activo = 1");
    $stmt->execute(['id' => $id]);
    $proyecto = $stmt->fetch();

    if (!$proyecto) {
        http_response_code(404);
        die('Project not found.');
    }

    $procesosCatalogo = $pdo->query("
        SELECT id, nombre, ponderacion
        FROM catalogo_procesos
        WHERE activo = 1
        ORDER BY orden
    ")->fetchAll();

    $sql = "
        SELECT
            c.id, c.nombre, c.ponderacion, c.orden,
            COALESCE(a.avance_ejecutado, 0) AS avance,
            a.fecha, a.observaciones,
            ROUND(c.ponderacion * COALESCE(a.avance_ejecutado, 0) / 100, 2) AS aporte
        FROM catalogo_procesos c
        LEFT JOIN (
            SELECT ad.proceso_id, ad.avance_ejecutado, ad.fecha, ad.observaciones
            FROM avance_detalle ad
            INNER JOIN (
                SELECT proceso_id, MAX(fecha) AS ultima_fecha
                FROM avance_detalle
                WHERE proyecto_id = :proyecto_id
                GROUP BY proceso_id
            ) ult
              ON ult.proceso_id = ad.proceso_id
             AND ult.ultima_fecha = ad.fecha
            WHERE ad.proyecto_id = :proyecto_id_2
        ) a ON a.proceso_id = c.id
        WHERE c.activo = 1
        ORDER BY c.orden
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['proyecto_id' => $id, 'proyecto_id_2' => $id]);
    $procesos = $stmt->fetchAll();

    $avanceGeneral = 0;
    foreach ($procesos as $proceso) {
        $avanceGeneral += (float)$proceso['aporte'];
    }
    $avanceGeneral = round($avanceGeneral, 2);

} catch (PDOException $e) {
    http_response_code(500);
    die("Error: " . htmlspecialchars($e->getMessage()));
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($proyecto['codigo']) ?> - Construction Progress</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#f4f6f9}.navbar-brand{font-weight:700}.card{border:0;border-radius:14px}
.progress{height:12px;border-radius:12px}.process-row{border-bottom:1px solid #e9ecef;padding:16px 0}
.process-row:last-child{border-bottom:0}.metric{font-size:1.8rem;font-weight:700}
</style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark mb-4">
<div class="container-fluid px-4">
<a href="index.php" class="navbar-brand text-decoration-none">🏗️ Construction Progress Dashboard</a>
<span class="text-white-50 small">Project Detail</span>
</div>
</nav>

<main class="container px-3 pb-5">
<a href="index.php" class="btn btn-outline-secondary mb-4">← Back to Dashboard</a>
<a href="history.php?id=<?= (int)$proyecto['id'] ?>" class="btn btn-outline-primary mb-4 ms-2">📈 View History</a>

<div class="card shadow-sm mb-4">
<div class="card-body p-4">
<div class="d-flex flex-wrap justify-content-between gap-3">
<div>
<div class="text-secondary"><?= htmlspecialchars($proyecto['codigo']) ?></div>
<h1 class="h2 mb-1"><?= htmlspecialchars($proyecto['nombre']) ?></h1>
<div class="text-secondary">📍 <?= htmlspecialchars($proyecto['ubicacion']) ?></div>
</div>
<div class="text-end">
<span class="badge text-bg-<?= $proyecto['estado']==='TERMINADO'?'success':($proyecto['estado']==='EN PROCESO'?'primary':'secondary') ?> fs-6">
<?= htmlspecialchars($proyecto['estado']) ?>
</span>
</div>
</div>
</div>
</div>

<div class="row g-3 mb-4">
<div class="col-md-4"><div class="card shadow-sm h-100"><div class="card-body">
<div class="text-secondary">Overall Progress</div><div class="metric"><?= number_format($avanceGeneral,1) ?>%</div>
<div class="progress mt-2"><div class="progress-bar" style="width:<?= min(100,$avanceGeneral) ?>%"></div></div>
</div></div></div>

<div class="col-md-4"><div class="card shadow-sm h-100"><div class="card-body">
<div class="text-secondary">Start Date</div><div class="metric fs-4"><?= $proyecto['fecha_inicio'] ? date('M d, Y',strtotime($proyecto['fecha_inicio'])) : '—' ?></div>
</div></div></div>

<div class="col-md-4"><div class="card shadow-sm h-100"><div class="card-body">
<div class="text-secondary">Estimated Completion</div><div class="metric fs-4"><?= $proyecto['fecha_fin_estimada'] ? date('M d, Y',strtotime($proyecto['fecha_fin_estimada'])) : '—' ?></div>
</div></div></div>
</div>

<?php if (isset($_GET['saved'])): ?>
<div class="alert alert-success shadow-sm" role="alert">
    ✅ Progress saved successfully.
</div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
<div class="card-body p-4">
<h2 class="h4 mb-3">Register Progress</h2>
<form method="post" action="save_progress.php" class="row g-3">
    <input type="hidden" name="proyecto_id" value="<?= (int)$proyecto['id'] ?>">

    <div class="col-md-4">
        <label class="form-label">Construction Process</label>
        <select name="proceso_id" class="form-select" required>
            <option value="">Select a process...</option>
            <?php foreach ($procesosCatalogo as $pc): ?>
                <option value="<?= (int)$pc['id'] ?>">
                    <?= htmlspecialchars($pc['nombre']) ?> (<?= number_format((float)$pc['ponderacion'], 1) ?>%)
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-md-2">
        <label class="form-label">Progress %</label>
        <input type="number" name="avance" class="form-control" min="0" max="100" step="0.01" required>
    </div>

    <div class="col-md-2">
        <label class="form-label">Date</label>
        <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>" required>
    </div>

    <div class="col-md-4">
        <label class="form-label">Observations</label>
        <input type="text" name="observaciones" class="form-control" maxlength="255" placeholder="Optional">
    </div>

    <div class="col-12">
        <button type="submit" class="btn btn-primary">Save Progress</button>
    </div>
</form>
</div>
</div>

<div class="card shadow-sm mb-4">
<div class="card-body p-4">
<h2 class="h4 mb-3">Progress by Process</h2>
<div id="processChart" style="height:420px;"></div>
</div>
</div>

<div class="card shadow-sm">
<div class="card-body p-4">
<h2 class="h4 mb-3">Construction Processes</h2>

<?php foreach ($procesos as $p): ?>
<?php $avance = min(100,max(0,(float)$p['avance'])); ?>
<div class="process-row">
<div class="row align-items-center g-3">
<div class="col-lg-4">
<strong><?= htmlspecialchars($p['nombre']) ?></strong>
<div class="small text-secondary">Weight: <?= number_format((float)$p['ponderacion'],1) ?>%</div>
</div>
<div class="col-lg-5">
<div class="d-flex justify-content-between small mb-1">
<span><?= number_format($avance,1) ?>%</span>
<span class="text-secondary">Contribution: <?= number_format((float)$p['aporte'],1) ?>%</span>
</div>
<div class="progress"><div class="progress-bar" style="width:<?= $avance ?>%"></div></div>
</div>
<div class="col-lg-3 small text-secondary">
<?= $p['fecha'] ? 'Updated: '.htmlspecialchars($p['fecha']) : 'No progress recorded' ?>
<?php if ($p['observaciones']): ?>
<br><?= htmlspecialchars($p['observaciones']) ?>
<?php endif; ?>
</div>
</div>
</div>
<?php endforeach; ?>

</div>
</div>

<div class="text-secondary small mt-4">Demo project — all data is fictional and intended for portfolio purposes.</div>
</main>

<script src="https://code.highcharts.com/highcharts.js"></script>
<script>
Highcharts.chart('processChart', {
    chart: { type: 'bar' },
    title: { text: null },
    xAxis: {
        categories: <?= json_encode(array_column($procesos, 'nombre')) ?>,
        title: { text: null }
    },
    yAxis: {
        min: 0,
        max: 100,
        title: { text: 'Progress (%)' }
    },
    tooltip: {
        valueSuffix: '%'
    },
    legend: { enabled: false },
    series: [{
        name: 'Progress',
        data: <?= json_encode(array_map(fn($p) => (float)$p['avance'], $procesos)) ?>
    }],
    credits: { enabled: false }
});
</script>

</body>
</html>
