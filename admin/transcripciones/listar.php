<?php

require_once '../../config/sesion.php';
verificarAdmin();
require_once '../../controllers/TranscripcionController.php';
$transcripcionConfig = require '../../config/transcripcion.php';

$controller = new TranscripcionController();
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
    'no_generada' => 0,
];
foreach ($videos as $item) {
    $key = $item['transcripcion_estado'] ?? 'no_generada';
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
        default => 'No generada',
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
            <h1>Transcripciones y subtitulos</h1>
            <p>Genera transcripciones locales con faster-whisper y reutilizalas como subtitulos y contexto de DEVIOZ AI.</p>
        </div>
        <div class="acciones-contenedor">
            <form method="POST" action="accion.php" onsubmit="return confirm('Encolar todos los videos sin transcripcion? El worker los procesara uno por uno.')">
                <?php echo csrfInput(); ?>
                <input type="hidden" name="accion" value="generar_todos">
                <button type="submit" class="btn-editar">Encolar pendientes</button>
            </form>
            <a class="btn" href="../../python/transcripcion/GUIA_RAPIDA.txt" target="_blank">Guia local</a>
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

        <div class="transcription-worker-card">
            <div>
                <span class="admin-nav-label">MOTOR LOCAL</span>
                <h3>Worker de transcripcion</h3>
                <p>Antes de generar transcripciones, ejecuta <code>python/transcripcion/INICIAR_WORKER.bat</code>. La cola seguira procesandose mientras esa ventana permanezca abierta.</p>
            </div>
            <div class="transcription-worker-actions">
                <span>Modelo configurado: <strong><?php echo htmlspecialchars((string)($transcripcionConfig['modelo'] ?? 'small')); ?></strong></span>
                <span>CPU: <strong>int8</strong></span>
            </div>
        </div>

        <div class="transcription-summary-grid">
            <article><strong><?php echo (int)$contadores['total']; ?></strong><span>Videos mostrados</span></article>
            <article><strong><?php echo (int)$contadores['completada']; ?></strong><span>Completadas</span></article>
            <article><strong><?php echo (int)$contadores['pendiente']; ?></strong><span>Pendientes</span></article>
            <article><strong><?php echo (int)$contadores['procesando']; ?></strong><span>Procesando</span></article>
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
                    <?php $estadoActual = $item['transcripcion_estado'] ?? 'no_generada'; ?>
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
                            <span class="transcription-status is-<?php echo htmlspecialchars($estadoActual); ?>"><?php echo estadoTranscripcionLabel($estadoActual); ?></span>
                            <?php if ($estadoActual === 'error' && !empty($item['mensaje_error'])): ?><small class="transcription-error-text"><?php echo htmlspecialchars($item['mensaje_error']); ?></small><?php endif; ?>
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
                            <?php elseif (in_array($estadoActual, ['pendiente','procesando'], true)): ?>
                                <span class="transcription-waiting">En cola local...</span>
                            <?php else: ?>
                                <form method="POST" action="accion.php" class="inline-delete-form">
                                    <?php echo csrfInput(); ?>
                                    <input type="hidden" name="accion" value="generar">
                                    <input type="hidden" name="id_video" value="<?php echo (int)$item['id_video']; ?>">
                                    <button class="btn-editar" type="submit">Generar</button>
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
if(document.querySelector('.transcription-status.is-procesando, .transcription-status.is-pendiente')){
    window.setTimeout(function(){ window.location.reload(); }, 6000);
}
</script>
</body>
</html>
