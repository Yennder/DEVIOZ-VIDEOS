<?php

require_once '../../config/sesion.php';
verificarAdmin();
require_once '../../controllers/TranscripcionController.php';
require_once '../../includes/TranscriptionWorkerManager.php';
$transcripcionConfig = require '../../config/transcripcion.php';

$controller = new TranscripcionController();
$workerManager = new TranscriptionWorkerManager();
$workerStatus = $workerManager->status();
$tablasOk = $controller->tablasDisponibles();
$buscar = trim($_GET['buscar'] ?? '');
$estado = trim($_GET['estado'] ?? '');
$videos = $tablasOk ? $controller->listarVideos($buscar, $estado) : [];
$mensaje = trim($_GET['msg'] ?? '');
$error = trim($_GET['error'] ?? '');

$contadores = [
    'total' => count($videos),
    'completada' => 0,
    'pendiente' => 0,
    'procesando' => 0,
    'error' => 0,
    'interrumpida' => 0,
    'no_generada' => 0,
];
foreach ($videos as $item) {
    $key = $item['transcripcion_estado'] ?? 'no_generada';
    if ($key === 'procesando' && empty($workerStatus['active'])) {
        $contadores['interrumpida']++;
        continue;
    }
    if (isset($contadores[$key])) {
        $contadores[$key]++;
    }
}

function estadoTranscripcionLabel(string $estado): string
{
    return match ($estado) {
        'completada' => 'Completada',
        'pendiente' => 'Pendiente',
        'procesando' => 'Procesando',
        'error' => 'Error',
        'interrumpida' => 'Interrumpida',
        default => 'No generada',
    };
}

function workerStateLabel(array $status): string
{
    return match ($status['state'] ?? 'detenido') {
        'activo' => 'Worker activo',
        'procesando' => 'Worker procesando',
        'interrumpido' => 'Worker interrumpido',
        default => 'Worker detenido',
    };
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Transcripciones - DEVIOZ VIDEOS</title>
<link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body>
<?php include '../includes/sidebar.php'; ?>
<div class="admin-main">
<?php include '../includes/navbar.php'; ?>
<section class="admin-content">
    <div class="gestion-header">
        <div>
            <span class="admin-nav-label">INTELIGENCIA DEL CONTENIDO</span>
            <h1>Transcripciones y subtitulos</h1>
            <p>Administra la cola local de faster-whisper, recupera trabajos interrumpidos y revisa el estado del motor.</p>
        </div>
        <div class="acciones-contenedor">
            <form method="POST" action="accion.php" onsubmit="return confirm('Encolar todos los videos sin transcripcion? El worker los procesara uno por uno.')">
                <?php echo csrfInput(); ?>
                <input type="hidden" name="accion" value="generar_todos">
                <button type="submit" class="btn-editar">Encolar pendientes</button>
            </form>
            <a class="btn" href="diagnostico.php">Diagnostico</a>
            <a class="btn" href="guia.php">Guia de instalacion</a>
        </div>
    </div>

    <?php if (!$tablasOk): ?>
        <div class="transcription-admin-alert is-error">
            <strong>Falta instalar la migracion V3.</strong>
            <span>Importa <code>database/migracion_transcripcion_v3.sql</code> en tu base <code>devioz_videos</code>.</span>
        </div>
    <?php else: ?>
        <?php if ($mensaje !== ''): ?><div class="transcription-admin-alert is-success"><?php echo htmlspecialchars($mensaje); ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="transcription-admin-alert is-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <div class="transcription-worker-card worker-v33-card <?php echo !empty($workerStatus['active']) ? 'is-online' : 'is-offline'; ?>">
            <div class="worker-v33-main">
                <div class="worker-v33-status-row">
                    <span class="worker-live-dot"></span>
                    <strong><?php echo htmlspecialchars(workerStateLabel($workerStatus)); ?></strong>
                    <span class="worker-v33-platform"><?php echo htmlspecialchars((string)($workerStatus['platform'] ?? PHP_OS_FAMILY)); ?></span>
                </div>
                <h3>Motor local de transcripcion</h3>
                <?php if (!empty($workerStatus['active'])): ?>
                    <p>
                        <?php if (!empty($workerStatus['current_video'])): ?>
                            Procesando video #<?php echo (int)$workerStatus['current_video']; ?><?php echo $workerStatus['current_title'] !== '' ? ': ' . htmlspecialchars($workerStatus['current_title']) : ''; ?>.
                            <?php if (!empty($workerStatus['message'])): ?>
                                <br><small><?php echo htmlspecialchars((string)$workerStatus['message']); ?></small>
                            <?php endif; ?>
                        <?php else: ?>
                            El worker esta listo y esperando trabajos pendientes.
                        <?php endif; ?>
                    </p>
                <?php else: ?>
                    <p>El worker no esta enviando heartbeat. Puedes iniciarlo desde aqui; ya no necesitas navegar hasta el archivo BAT.</p>
                <?php endif; ?>
            </div>

            <div class="worker-v33-meta">
                <span>Modelo <strong><?php echo htmlspecialchars((string)($transcripcionConfig['modelo'] ?? 'small')); ?></strong></span>
                <span>Dispositivo <strong><?php echo htmlspecialchars((string)($workerStatus['device'] ?: 'cpu')); ?></strong></span>
                <span>PID <strong><?php echo !empty($workerStatus['pid']) ? (int)$workerStatus['pid'] : '-'; ?></strong></span>
                <span>Heartbeat <strong><?php echo isset($workerStatus['heartbeat_age']) && $workerStatus['heartbeat_age'] !== null ? ((int)$workerStatus['heartbeat_age'] . ' s') : '-'; ?></strong></span>
            </div>

            <div class="worker-v33-buttons">
                <?php if (!empty($workerStatus['active'])): ?>
                    <form method="POST" action="worker_control.php" onsubmit="return confirm('Reiniciar el worker? El trabajo en curso volvera a Pendiente y se procesara nuevamente.')">
                        <?php echo csrfInput(); ?><input type="hidden" name="accion" value="reiniciar">
                        <button type="submit" class="btn-editar">Reiniciar</button>
                    </form>
                    <form method="POST" action="worker_control.php" onsubmit="return confirm('Detener el worker? Los trabajos en curso quedaran pendientes para la proxima ejecucion.')">
                        <?php echo csrfInput(); ?><input type="hidden" name="accion" value="detener">
                        <button type="submit" class="btn-eliminar">Detener</button>
                    </form>
                <?php else: ?>
                    <form method="POST" action="worker_control.php">
                        <?php echo csrfInput(); ?><input type="hidden" name="accion" value="iniciar">
                        <button type="submit" class="btn-editar">&#9654; Iniciar worker</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($contadores['interrumpida'] > 0): ?>
        <div class="transcription-admin-alert is-warning">
            <strong><?php echo (int)$contadores['interrumpida']; ?> trabajo(s) quedaron interrumpidos.</strong>
            <span>Al iniciar el worker V3.3 se recuperan automaticamente. Tambien puedes usar Reintentar en cada fila.</span>
        </div>
        <?php endif; ?>

        <div class="transcription-summary-grid worker-v33-summary">
            <article><strong><?php echo (int)$contadores['total']; ?></strong><span>Videos mostrados</span></article>
            <article><strong><?php echo (int)$contadores['completada']; ?></strong><span>Completadas</span></article>
            <article><strong><?php echo (int)$contadores['pendiente']; ?></strong><span>Pendientes</span></article>
            <article><strong><?php echo (int)$contadores['procesando']; ?></strong><span>Procesando</span></article>
            <article><strong><?php echo (int)$contadores['interrumpida']; ?></strong><span>Interrumpidas</span></article>
            <article><strong><?php echo (int)$contadores['error']; ?></strong><span>Con error</span></article>
        </div>

        <form method="GET" class="filtros-admin transcription-filters">
            <div class="filtro-busqueda">
                <label>Buscar</label>
                <input type="text" name="buscar" value="<?php echo htmlspecialchars($buscar); ?>" placeholder="Titulo, categoria o serie...">
            </div>
            <div>
                <label>Estado</label>
                <select name="estado">
                    <option value="">Todos</option>
                    <?php foreach (['no_generada','pendiente','procesando','completada','error'] as $opcion): ?>
                    <option value="<?php echo $opcion; ?>" <?php echo $estado === $opcion ? 'selected' : ''; ?>><?php echo estadoTranscripcionLabel($opcion); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filtros-botones">
                <button type="submit" class="btn-filtrar">Filtrar</button>
                <a href="listar.php" class="btn-limpiar">Limpiar</a>
            </div>
        </form>

        <div class="table-scroll-shell">
        <table class="admin-table transcription-table">
            <thead>
            <tr>
                <th>Video</th>
                <th>Tipo</th>
                <th>Estado</th>
                <th>Progreso</th>
                <th>Modelo</th>
                <th>Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$videos): ?>
                <tr><td colspan="6" class="tabla-vacia">No hay videos con estos filtros.</td></tr>
            <?php else: ?>
                <?php foreach ($videos as $item): ?>
                    <?php
                    $estadoActual = $item['transcripcion_estado'] ?? 'no_generada';
                    $interrumpida = $estadoActual === 'procesando' && empty($workerStatus['active']);
                    $estadoVisual = $interrumpida ? 'interrumpida' : $estadoActual;
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($item['titulo']); ?></strong>
                            <small class="transcription-subline">ID <?php echo (int)$item['id_video']; ?> · <?php echo htmlspecialchars($item['categoria']); ?></small>
                            <?php if (!empty($item['serie'])): ?>
                                <small class="transcription-subline"><?php echo htmlspecialchars($item['serie']); ?> · T<?php echo (int)$item['numero_temporada']; ?> C<?php echo (int)$item['numero_capitulo']; ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $item['tipo_contenido'] === 'serie' ? 'Capitulo' : 'Video'; ?></td>
                        <td>
                            <span class="transcription-status is-<?php echo htmlspecialchars($estadoVisual); ?>"><?php echo estadoTranscripcionLabel($estadoVisual); ?></span>
                            <?php if ($estadoActual === 'error' && !empty($item['mensaje_error'])): ?><small class="transcription-error-text"><?php echo htmlspecialchars($item['mensaje_error']); ?></small><?php endif; ?>
                            <?php if ($interrumpida): ?><small class="transcription-error-text">El proceso anterior ya no esta activo.</small><?php endif; ?>
                        </td>
                        <td>
                            <div class="transcription-progress"><i style="width:<?php echo max(0, min(100, (int)$item['progreso'])); ?>%"></i></div>
                            <small><?php echo (int)$item['progreso']; ?>%<?php if ((int)$item['segmentos'] > 0): ?> · <?php echo (int)$item['segmentos']; ?> segmentos<?php endif; ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($item['modelo'] ?: '-'); ?></td>
                        <td class="acciones">
                            <div class="acciones-contenedor transcription-actions">
                            <?php if ($estadoActual === 'completada'): ?>
                                <a class="btn-editar" href="editar.php?id_video=<?php echo (int)$item['id_video']; ?>">Ver / editar</a>
                                <a class="btn-editar" href="descargar_vtt.php?id_video=<?php echo (int)$item['id_video']; ?>">VTT</a>
                                <form method="POST" action="accion.php" class="inline-delete-form" onsubmit="return confirm('Se reemplazara la transcripcion actual. Continuar?')">
                                    <?php echo csrfInput(); ?>
                                    <input type="hidden" name="accion" value="regenerar">
                                    <input type="hidden" name="id_video" value="<?php echo (int)$item['id_video']; ?>">
                                    <button class="btn-eliminar" type="submit">Regenerar</button>
                                </form>
                            <?php elseif ($interrumpida): ?>
                                <form method="POST" action="accion.php" class="inline-delete-form">
                                    <?php echo csrfInput(); ?>
                                    <input type="hidden" name="accion" value="reintentar">
                                    <input type="hidden" name="id_video" value="<?php echo (int)$item['id_video']; ?>">
                                    <button class="btn-editar" type="submit">Reintentar</button>
                                </form>
                            <?php elseif ($estadoActual === 'pendiente'): ?>
                                <span class="transcription-waiting"><?php echo !empty($workerStatus['active']) ? 'En cola local...' : 'Esperando worker'; ?></span>
                            <?php elseif ($estadoActual === 'procesando'): ?>
                                <span class="transcription-waiting">Procesando localmente...</span>
                            <?php else: ?>
                                <form method="POST" action="accion.php" class="inline-delete-form">
                                    <?php echo csrfInput(); ?>
                                    <input type="hidden" name="accion" value="generar">
                                    <input type="hidden" name="id_video" value="<?php echo (int)$item['id_video']; ?>">
                                    <button class="btn-editar" type="submit"><?php echo $estadoActual === 'error' ? 'Reintentar' : 'Generar'; ?></button>
                                </form>
                            <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</section>
</div>
<script>
if(document.querySelector('.transcription-status.is-procesando, .transcription-status.is-pendiente') || <?php echo !empty($workerStatus['active']) ? 'true' : 'false'; ?>){
    window.setTimeout(function(){ window.location.reload(); }, 6000);
}
</script>
</body>
</html>
