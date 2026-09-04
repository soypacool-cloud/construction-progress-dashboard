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

    $stmt = $pdo->prepare("
        SELECT
            ad.fecha,
            c.nombre AS proceso,
            c.ponderacion,
            ad.avance_ejecutado,
            ROUND(c.ponderacion * ad.avance_ejecutado / 100, 2) AS aporte,
            ad.observaciones
        FROM avance_detalle ad
        INNER JOIN catalogo_procesos c ON c.id = ad.proceso_id
        WHERE ad.proyecto_id = :id
        ORDER BY ad.fecha DESC, c.orden ASC
    ");
    $stmt->execute(['id' => $id]);
    $historial = $stmt->fetchAll();

    $dates = [];
    $progress = [];

    // Snapshot by date: latest recorded value for each process up to that date.
    $dateStmt = $pdo->prepare("
        SELECT DISTINCT fecha
        FROM avance_detalle
        WHERE proyecto_id = :id
        ORDER BY fecha ASC
    ");
    $dateStmt->execute(['id' => $id]);
    $fechas = $dateStmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($fechas as $fecha) {
        $snapshotStmt = $pdo->prepare("
            SELECT COALESCE(SUM(c.ponderacion * x.avance_ejecutado / 100), 0)
            FROM catalogo_procesos c
            LEFT JOIN (
                SELECT ad1.proceso_id, ad1.avance_ejecutado
                FROM avance_detalle ad1
                INNER JOIN (
                    SELECT proceso_id, MAX(fecha) AS max_fecha
                    FROM avance_detalle
                    WHERE proyecto_id = :id1 AND fecha <= :fecha1
                    GROUP BY proceso_id
                ) latest
                  ON latest.proceso_id = ad1.proceso_id
                 AND latest.max_fecha = ad1.fecha
                WHERE ad1.proyecto_id = :id2
            ) x ON x.proceso_id = c.id
            WHERE c.activo = 1
        ");
        $snapshotStmt->execute([
            'id1' => $id,
            'fecha1' => $fecha,
            'id2' => $id
        ]);

        $dates[] = date('M d', strtotime($fecha));
        $progress[] = round((float)$snapshotStmt->fetchColumn(), 2);
    }

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
<title>Progress History - <?= htmlspecialchars($proyecto['codigo']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#f4f6f9}.navbar-brand{font-weight:700}.card{border:0;border-radius:14px}
</style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark mb-4">
<div class="container-fluid px-4">
<a href="index.php" class="navbar-brand text-decoration-none">🏗️ Construction Progress Dashboard</a>
<span class="text-white-50 small">Progress History</span>
</div>
</nav>

<main class="container pb-5">
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
<div>
<div class="text-secondary"><?= htmlspecialchars($proyecto['codigo']) ?></div>
<h1 class="h2 mb-1"><?= htmlspecialchars($proyecto['nombre']) ?></h1>
</div>
<div>
<a href="detail.php?id=<?= (int)$id ?>" class="btn btn-outline-secondary">← Project Detail</a>
</div>
</div>

<div class="card shadow-sm mb-4">
<div class="card-body p-4">
<h2 class="h4">Progress Evolution</h2>
<p class="text-secondary">Weighted project progress based on the latest value of each process at each date.</p>
<div id="historyChart" style="height:380px;"></div>
</div>
</div>

<div class="card shadow-sm">
<div class="card-body p-4">
<h2 class="h4 mb-3">Recorded Updates</h2>
<div class="table-responsive">
<table class="table table-hover align-middle">
<thead>
<tr>
<th>Date</th><th>Process</th><th>Weight</th><th>Executed</th><th>Contribution</th><th>Observations</th>
</tr>
</thead>
<tbody>
<?php foreach ($historial as $row): ?>
<tr>
<td><?= htmlspecialchars($row['fecha']) ?></td>
<td><strong><?= htmlspecialchars($row['proceso']) ?></strong></td>
<td><?= number_format((float)$row['ponderacion'],1) ?>%</td>
<td><?= number_format((float)$row['avance_ejecutado'],1) ?>%</td>
<td><?= number_format((float)$row['aporte'],1) ?>%</td>
<td><?= htmlspecialchars($row['observaciones'] ?? '') ?></td>
</tr>
<?php endforeach; ?>
<?php if (!$historial): ?>
<tr><td colspan="6" class="text-center text-secondary py-4">No progress records found.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
</div>

<div class="text-secondary small mt-4">Demo project — all data is fictional and intended for portfolio purposes.</div>
</main>

<script src="https://code.highcharts.com/highcharts.js"></script>
<script>
Highcharts.chart('historyChart', {
    chart: { type: 'line' },
    title: { text: null },
    xAxis: {
        categories: <?= json_encode($dates) ?>
    },
    yAxis: {
        min: 0,
        max: 100,
        title: { text: 'Overall Progress (%)' }
    },
    tooltip: {
        valueSuffix: '%'
    },
    legend: { enabled: false },
    series: [{
        name: 'Overall Progress',
        data: <?= json_encode($progress) ?>
    }],
    credits: { enabled: false }
});
</script>
</body>
</html>
