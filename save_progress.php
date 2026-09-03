<?php
$config = require __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$proyectoId = filter_input(INPUT_POST, 'proyecto_id', FILTER_VALIDATE_INT);
$procesoId = filter_input(INPUT_POST, 'proceso_id', FILTER_VALIDATE_INT);
$avance = filter_input(INPUT_POST, 'avance', FILTER_VALIDATE_FLOAT);
$fecha = $_POST['fecha'] ?? '';
$observaciones = trim($_POST['observaciones'] ?? '');

if (!$proyectoId || !$procesoId || $avance === false || $avance < 0 || $avance > 100) {
    die('Invalid data. Progress must be between 0 and 100.');
}

$date = DateTime::createFromFormat('Y-m-d', $fecha);
if (!$date || $date->format('Y-m-d') !== $fecha) {
    die('Invalid date.');
}

try {
    $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
    $pdo = new PDO($dsn, $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $check = $pdo->prepare("
        SELECT COUNT(*)
        FROM proyectos p
        CROSS JOIN catalogo_procesos c
        WHERE p.id = :proyecto_id
          AND c.id = :proceso_id
          AND p.activo = 1
          AND c.activo = 1
    ");
    $check->execute([
        'proyecto_id' => $proyectoId,
        'proceso_id' => $procesoId
    ]);

    if ((int)$check->fetchColumn() !== 1) {
        die('Project or process not found.');
    }

    $stmt = $pdo->prepare("
        INSERT INTO avance_detalle
            (proyecto_id, proceso_id, avance_ejecutado, fecha, observaciones)
        VALUES
            (:proyecto_id, :proceso_id, :avance, :fecha, :observaciones)
        ON DUPLICATE KEY UPDATE
            avance_ejecutado = VALUES(avance_ejecutado),
            observaciones = VALUES(observaciones)
    ");

    $stmt->execute([
        'proyecto_id' => $proyectoId,
        'proceso_id' => $procesoId,
        'avance' => $avance,
        'fecha' => $fecha,
        'observaciones' => $observaciones !== '' ? $observaciones : null
    ]);

    header("Location: detail.php?id={$proyectoId}&saved=1");
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    die("Error saving progress: " . htmlspecialchars($e->getMessage()));
}
