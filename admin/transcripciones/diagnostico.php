<?php

require_once '../../config/sesion.php';
verificarAdmin();
require_once '../../includes/TranscriptionWorkerManager.php';

$manager = new TranscriptionWorkerManager();
$diagnostico = $manager->diagnostics();
$details = is_array($diagnostico['details'] ?? null) ? $diagnostico['details'] : [];
$worker = $diagnostico['worker'] ?? [];
$log = $manager->tailLog(25);

function diagBool($value): bool
{
    return $value === true || $value === 1 || $value === '1' || $value === 'ok' || $value === 'OK';
}

$checks = [
    ['Python local', (bool)($diagnostico['python_ready'] ?? false), $details['python_version'] ?? 'No detectado'],
    ['faster-whisper', diagBool($details['faster_whisper'] ?? false), !empty($details['faster_whisper_version']) ? $details['faster_whisper_version'] : 'Libreria'],
    ['PyAV', diagBool($details['pyav'] ?? false), $details['pyav_version'] ?? 'Decodificacion multimedia'],
    ['MySQL', diagBool($details['mysql'] ?? false), isset($details['video_count']) ? ((int)$details['video_count'] . ' videos') : 'Conexion'],
    ['Migracion V3', diagBool($details['migration_v3'] ?? false), 'Tablas de transcripcion'],
    ['FFmpeg', diagBool($details['ffmpeg'] ?? false), !empty($details['ffmpeg_path']) ? $details['ffmpeg_path'] : 'Opcional: PyAV puede reemplazarlo'],
    ['Modelo ' . htmlspecialchars((string)($details['model_name'] ?? 'small')), diagBool($details['model_cached'] ?? false), diagBool($details['model_cached'] ?? false) ? 'Disponible en cache local' : 'Se descargara al primer uso'],
    ['Worker', (bool)($worker['active'] ?? false), ucfirst((string)($worker['state'] ?? 'detenido'))],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Diagnostico de transcripcion - DEVIOZ VIDEOS</title>
<link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body>
<?php include '../includes/sidebar.php'; ?>
<div class="admin-main">
<?php include '../includes/navbar.php'; ?>
<section class="admin-content">
    <div class="gestion-header">
        <div>
            <span class="admin-nav-label">TECHFLIX V3.3</span>
            <h1>Diagnostico del motor local</h1>
            <p>Comprueba que el equipo tenga lo necesario para transcribir videos sin depender de servicios externos.</p>
        </div>
        <div class="acciones-contenedor">
            <a href="listar.php" class="btn">Volver</a>
            <a href="diagnostico.php" class="btn-editar">Actualizar diagnostico</a>
            <a href="guia.php" class="btn">Guia de instalacion</a>
        </div>
    </div>

    <?php if (!empty($diagnostico['error'])): ?>
        <div class="transcription-admin-alert is-error"><?php echo htmlspecialchars((string)$diagnostico['error']); ?></div>
    <?php endif; ?>

    <div class="worker-diagnostic-grid">
        <?php foreach ($checks as [$label, $ok, $detail]): ?>
        <article class="worker-diagnostic-card <?php echo $ok ? 'is-ok' : 'is-warning'; ?>">
            <div class="worker-diagnostic-icon"><?php echo $ok ? '&#10003;' : '!'; ?></div>
            <div>
                <strong><?php echo $label; ?></strong>
                <span><?php echo htmlspecialchars((string)$detail); ?></span>
            </div>
        </article>
        <?php endforeach; ?>
    </div>

    <div class="worker-diagnostic-summary">
        <div>
            <span class="admin-nav-label">ESTADO ACTUAL</span>
            <h3><?php echo !empty($worker['active']) ? 'Worker disponible' : 'Worker detenido'; ?></h3>
            <p><?php echo !empty($worker['active']) ? 'El motor esta enviando heartbeat y puede procesar la cola.' : 'Puedes iniciarlo desde Transcripciones cuando el diagnostico principal este correcto.'; ?></p>
        </div>
        <div class="worker-diagnostic-meta">
            <span>Plataforma <strong><?php echo htmlspecialchars((string)($worker['platform'] ?? PHP_OS_FAMILY)); ?></strong></span>
            <span>PID <strong><?php echo !empty($worker['pid']) ? (int)$worker['pid'] : '-'; ?></strong></span>
            <span>Heartbeat <strong><?php echo isset($worker['heartbeat_age']) && $worker['heartbeat_age'] !== null ? ((int)$worker['heartbeat_age'] . ' s') : '-'; ?></strong></span>
        </div>
    </div>

    <?php if ($log !== ''): ?>
    <div class="worker-log-card">
        <div class="worker-log-title"><strong>Ultimos mensajes del worker</strong><span>runtime/worker.log</span></div>
        <pre><?php echo htmlspecialchars($log); ?></pre>
    </div>
    <?php endif; ?>
</section>
</div>
</body>
</html>
