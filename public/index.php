<?php
$config = require __DIR__ . '/../config/database.php';

try {
    $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
    $pdo = new PDO($dsn, $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $sql = "
        SELECT
            p.id, p.codigo, p.nombre, p.ubicacion, p.estado,
            COALESCE(ROUND(SUM(c.ponderacion * COALESCE(a.avance_ejecutado, 0) / 100), 2), 0) AS avance
        FROM proyectos p
        CROSS JOIN catalogo_procesos c
        LEFT JOIN (
            SELECT ad.proyecto_id, ad.proceso_id, ad.avance_ejecutado
            FROM avance_detalle ad
            INNER JOIN (
                SELECT proyecto_id, proceso_id, MAX(fecha) AS ultima_fecha
                FROM avance_detalle
                GROUP BY proyecto_id, proceso_id
            ) ult
              ON ult.proyecto_id = ad.proyecto_id
             AND ult.proceso_id = ad.proceso_id
             AND ult.ultima_fecha = ad.fecha
        ) a ON a.proyecto_id = p.id AND a.proceso_id = c.id
        WHERE p.activo = 1 AND c.activo = 1
        GROUP BY p.id, p.codigo, p.nombre, p.ubicacion, p.estado
        ORDER BY p.id
    ";

    $proyectos = $pdo->query($sql)->fetchAll();

    $totalProyectos = count($proyectos);
    $terminados = 0;
    $enProceso = 0;
    $sumaAvance = 0;

    foreach ($proyectos as $proyecto) {
        $sumaAvance += (float)$proyecto['avance'];
        if ($proyecto['estado'] === 'TERMINADO') $terminados++;
        elseif ($proyecto['estado'] === 'EN PROCESO') $enProceso++;
    }

    $avanceGeneral = $totalProyectos ? round($sumaAvance / $totalProyectos, 1) : 0;

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
<title>Construction Progress Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.css" rel="stylesheet">
<style>
body{background:#f4f6f9}.navbar-brand{font-weight:700}.dashboard-title{font-weight:700}
.card-kpi,.table-card{border:0;border-radius:14px}.kpi-value{font-size:2rem;font-weight:700}
.progress{height:10px;border-radius:10px}.project-link{text-decoration:none;font-weight:600}
</style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark mb-4">
<div class="container-fluid px-4">
<span class="navbar-brand">🏗️ Construction Progress Dashboard</span>
<span class="text-white-50 small">Portfolio Demo</span>
</div>
</nav>

<main class="container-fluid px-4 pb-5">
<div class="mb-4">
<h1 class="dashboard-title mb-1">Construction Overview</h1>
<p class="text-secondary mb-0">Project progress monitoring using weighted construction processes.</p>
</div>

<div class="card shadow-sm border-0 rounded-4 mb-4">
<div class="card-body">
<div class="row g-3">
    <div class="col-md-5">
        <label for="statusFilter" class="form-label">Filter by status</label>
        <select id="statusFilter" class="form-select">
            <option value="">All statuses</option>
            <option value="EN PROCESO">EN PROCESO</option>
            <option value="TERMINADO">TERMINADO</option>
            <option value="PENDIENTE">PENDIENTE</option>
        </select>
    </div>
    <div class="col-md-5">
        <label for="locationFilter" class="form-label">Filter by location</label>
        <select id="locationFilter" class="form-select">
            <option value="">All locations</option>
            <?php
            $locations = array_values(array_unique(array_column($proyectos, 'ubicacion')));
            sort($locations);
            foreach ($locations as $location):
            ?>
                <option value="<?= htmlspecialchars($location) ?>"><?= htmlspecialchars($location) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2 d-flex align-items-end">
        <button type="button" id="clearFilters" class="btn btn-outline-secondary w-100">Clear</button>
    </div>
</div>
</div>
</div>

<div class="row g-3 mb-4">
<div class="col-md-6 col-xl-3"><div class="card card-kpi shadow-sm h-100"><div class="card-body">
<div class="text-secondary">Total Projects</div><div class="kpi-value"><?= $totalProyectos ?></div>
</div></div></div>
<div class="col-md-6 col-xl-3"><div class="card card-kpi shadow-sm h-100"><div class="card-body">
<div class="text-secondary">Overall Progress</div><div class="kpi-value"><?= number_format($avanceGeneral,1) ?>%</div>
</div></div></div>
<div class="col-md-6 col-xl-3"><div class="card card-kpi shadow-sm h-100"><div class="card-body">
<div class="text-secondary">In Progress</div><div class="kpi-value"><?= $enProceso ?></div>
</div></div></div>
<div class="col-md-6 col-xl-3"><div class="card card-kpi shadow-sm h-100"><div class="card-body">
<div class="text-secondary">Completed</div><div class="kpi-value"><?= $terminados ?></div>
</div></div></div>
</div>

<div class="row g-4">
<div class="col-xl-8">
<div class="card table-card shadow-sm">
<div class="card-body">
<h5 class="mb-3">Projects</h5>
<div class="table-responsive">
<table id="projectsTable" class="table table-hover align-middle">
<thead><tr><th>Code</th><th>Project</th><th>Location</th><th>Status</th><th style="min-width:180px">Progress</th></tr></thead>
<tbody>
<?php foreach ($proyectos as $p): ?>
<?php
$avance = min(100,max(0,(float)$p['avance']));
$badge = $p['estado']==='TERMINADO'?'success':($p['estado']==='EN PROCESO'?'primary':'secondary');
?>
<tr data-status="<?= htmlspecialchars($p['estado']) ?>" data-location="<?= htmlspecialchars($p['ubicacion']) ?>">
<td><a class="project-link" href="detail.php?id=<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['codigo']) ?></a></td>
<td><a class="text-decoration-none text-dark" href="detail.php?id=<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></a></td>
<td><?= htmlspecialchars($p['ubicacion']) ?></td>
<td><span class="badge text-bg-<?= $badge ?>"><?= htmlspecialchars($p['estado']) ?></span></td>
<td>
<div class="d-flex justify-content-between small mb-1"><span><?= number_format($avance,1) ?>%</span></div>
<div class="progress"><div class="progress-bar" style="width:<?= $avance ?>%"></div></div>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div></div>
</div>

<div class="col-xl-4">
<div class="card table-card shadow-sm h-100"><div class="card-body">
<h5 class="mb-3">Progress by Project</h5><div id="progressChart" style="height:360px"></div>
</div></div>
</div>
</div>

<div class="text-secondary small mt-4">Demo project — all data is fictional and intended for portfolio purposes.</div>
</main>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.js"></script>
<script src="https://code.highcharts.com/highcharts.js"></script>
<script>
const table = new DataTable('#projectsTable',{pageLength:10,language:{
search:'Search:',lengthMenu:'_MENU_ projects per page',
info:'Showing _START_ to _END_ of _TOTAL_ projects',emptyTable:'No projects available'
}});

function applyProjectFilters() {
    const status = document.getElementById('statusFilter').value;
    const location = document.getElementById('locationFilter').value;

    table.rows().every(function() {
        const row = this.node();
        const matchesStatus = !status || row.dataset.status === status;
        const matchesLocation = !location || row.dataset.location === location;
        row.style.display = (matchesStatus && matchesLocation) ? '' : 'none';
    });
}

document.getElementById('statusFilter').addEventListener('change', applyProjectFilters);
document.getElementById('locationFilter').addEventListener('change', applyProjectFilters);

document.getElementById('clearFilters').addEventListener('click', function() {
    document.getElementById('statusFilter').value = '';
    document.getElementById('locationFilter').value = '';
    applyProjectFilters();
});
Highcharts.chart('progressChart',{
chart:{type:'bar'},title:{text:null},
xAxis:{categories:<?= json_encode(array_column($proyectos,'codigo')) ?>},
yAxis:{min:0,max:100,title:{text:'Progress (%)'}},
tooltip:{valueSuffix:'%'},legend:{enabled:false},
series:[{name:'Progress',data:<?= json_encode(array_map(fn($p)=>(float)$p['avance'],$proyectos)) ?>}],
credits:{enabled:false}
});
</script>
</body>
</html>
