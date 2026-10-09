<?php
require_once __DIR__ . '/../../config/sesion.php';
verificarAdmin();
require_once __DIR__ . '/../../models/CuestionarioVideoReportes.php';

$h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$idVideo = max(0, filter_var($_GET['id_video'] ?? 0, FILTER_VALIDATE_INT) ?: 0);
$idUsuario = max(0, filter_var($_GET['id_usuario'] ?? 0, FILTER_VALIDATE_INT) ?: 0);
$pagina = min(100000, max(1, filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT) ?: 1));
$porPagina = 25;
$instalado = false;
$error = false;
$opciones = ['videos' => [], 'usuarios' => []];
$estadisticas = ['total_intentos' => 0, 'participantes' => 0, 'videos_evaluados' => 0];
$total = 0;
$filas = $intentos = [];
try {
    $modelo = new CuestionarioVideoReportes();
    $instalado = $modelo->instalado();
    if ($instalado) {
        $opciones = $modelo->opcionesAdmin();
        $estadisticas = $modelo->estadisticasAdmin($idVideo, $idUsuario);
        $total = $modelo->contarResultadosAdmin($idVideo, $idUsuario);
        $pagina = min($pagina, max(1, (int)ceil($total / $porPagina)));
        $filas = $modelo->resultadosAdmin($idVideo, $idUsuario, $porPagina, ($pagina - 1) * $porPagina);
        if ($idVideo && $idUsuario && ($_GET['detalle'] ?? '') === '1') {
            $intentos = $modelo->intentosAdmin($idVideo, $idUsuario);
        }
    }
} catch (Throwable $e) {
    error_log('DEVIOZ V4.5.4.1 - Notas administracion: ' . $e->getMessage());
    $error = true;
}
$urlReporte = static fn(array $valores): string => 'resultados_cuestionarios.php?' . http_build_query(array_merge([
    'id_video' => $idVideo, 'id_usuario' => $idUsuario,
], $valores));
?>
<!DOCTYPE html><html lang="es"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notas de cuestionarios - DEVIOZ VIDEOS</title>
<link rel="stylesheet" href="../../assets/css/admin.css">
<link rel="stylesheet" href="../../assets/css/cuestionarios.css?v=4.5.4.1">
</head><body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="admin-main">
<?php include __DIR__ . '/../includes/navbar.php'; ?>
<main class="admin-content vq-admin vq-report-admin">
    <div class="gestion-header">
        <div><span class="vq-eyebrow">Seguimiento de aprendizaje · V4.5.4.1</span><h1>Notas por video</h1><p>Resultados de los cuestionarios públicos de cinco preguntas. No se mezclan con exámenes de Learning Lab.</p></div>
        <div class="acciones-contenedor"><a class="btn-limpiar" href="listar.php">Volver a videos</a></div>
    </div>
    <?php if (!$instalado || $error): ?>
        <div role="alert" class="vq-alert is-error"><?php echo $error ? 'No fue posible consultar las notas. Revisa la conexión a MariaDB.' : 'Las tablas de V4.5.4 todavía no están instaladas.'; ?></div>
    <?php else: ?>
        <form method="GET" class="vq-admin-card vq-report-filters" aria-label="Filtrar resultados">
            <label>Video
                <select name="id_video"><option value="0">Todos los videos</option>
                    <?php foreach ($opciones['videos'] as $video): ?>
                        <option value="<?php echo (int)$video['id_video']; ?>" <?php echo $idVideo === (int)$video['id_video'] ? 'selected' : ''; ?>><?php echo $h($video['titulo']); ?> (#<?php echo (int)$video['id_video']; ?>)</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Participante
                <select name="id_usuario"><option value="0">Todos los participantes</option>
                    <?php foreach ($opciones['usuarios'] as $usuario): ?>
                        <option value="<?php echo (int)$usuario['id_usuario']; ?>" <?php echo $idUsuario === (int)$usuario['id_usuario'] ? 'selected' : ''; ?>><?php echo $h($usuario['nombre']); ?> (#<?php echo (int)$usuario['id_usuario']; ?>)</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="vq-report-filter-actions"><button class="btn" type="submit">Filtrar notas</button><a class="btn-limpiar" href="resultados_cuestionarios.php">Limpiar</a></div>
        </form>
        <section class="vq-report-stats" aria-label="Indicadores de resultados">
            <article><span>Intentos registrados</span><strong><?php echo (int)$estadisticas['total_intentos']; ?></strong><small>Incluye versiones anteriores</small></article>
            <article><span>Participantes</span><strong><?php echo (int)$estadisticas['participantes']; ?></strong><small>Usuarios con respuestas</small></article>
            <article><span>Videos evaluados</span><strong><?php echo (int)$estadisticas['videos_evaluados']; ?></strong><small>Con al menos un intento</small></article>
        </section>
        <section class="vq-admin-card">
            <div class="vq-report-heading"><div><span class="vq-eyebrow">Resultados por participante</span><h2>Calificaciones registradas</h2></div><span class="vq-score-pill"><?php echo $total; ?> registros</span></div>
            <?php if (!$filas): ?>
                <div class="vq-report-empty"><h3>Aún no hay resultados</h3><p>Cuando un usuario finalice las cinco preguntas, sus notas aparecerán aquí.</p></div>
            <?php else: ?>
                <div class="vq-report-scroll"><table class="vq-report-table">
                    <thead><tr><th>Participante</th><th>Video</th><th>Intentos</th><th>Mejor nota</th><th>Última nota</th><th>Última evaluación</th><th>Acciones</th></tr></thead>
                    <tbody><?php foreach ($filas as $fila): ?>
                        <tr>
                            <td><strong><?php echo $h($fila['nombre']); ?></strong></td>
                            <td><?php echo $h($fila['titulo']); ?></td>
                            <td><?php echo (int)$fila['intentos']; ?></td>
                            <td><strong class="vq-report-score"><?php echo (int)$fila['mejor_nota']; ?>/100</strong></td>
                            <td><?php echo (int)$fila['ultima_nota']; ?>/100</td>
                            <td><?php echo $h(date('d/m/Y H:i', strtotime((string)$fila['ultima_fecha']))); ?></td>
                            <td><a class="vq-secondary" href="<?php echo $h($urlReporte(['id_video' => (int)$fila['id_video'], 'id_usuario' => (int)$fila['id_usuario'], 'detalle' => 1, 'pagina' => 1])); ?>#detalle-intentos">Ver intentos</a></td>
                        </tr>
                    <?php endforeach; ?></tbody>
                </table></div>
                <?php if ($total > $porPagina): ?>
                    <nav class="vq-pagination" aria-label="Páginas de resultados">
                        <?php if ($pagina > 1): ?><a href="<?php echo $h($urlReporte(['pagina' => $pagina-1])); ?>">← Anterior</a><?php endif; ?>
                        <span>Página <?php echo $pagina; ?> de <?php echo (int)ceil($total / $porPagina); ?></span>
                        <?php if ($pagina * $porPagina < $total): ?><a href="<?php echo $h($urlReporte(['pagina' => $pagina+1])); ?>">Siguiente →</a><?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </section>
        <?php if ($idVideo && $idUsuario && ($_GET['detalle'] ?? '') === '1'): ?>
            <section class="vq-admin-card" id="detalle-intentos">
                <div class="vq-report-heading"><div><span class="vq-eyebrow">Historial detallado</span><h2>Intentos de este participante y video</h2></div><a class="vq-secondary" href="<?php echo $h($urlReporte(['detalle' => 0])); ?>">Cerrar detalle</a></div>
                <?php if (!$intentos): ?><p>No se encontraron intentos con estos filtros.</p>
                <?php else: ?>
                    <div class="vq-report-scroll"><table class="vq-report-table">
                        <thead><tr><th>Intento</th><th>Versión</th><th>Aciertos</th><th>Nota</th><th>Fecha</th></tr></thead>
                        <tbody><?php foreach ($intentos as $intento): ?>
                            <tr><td>#<?php echo (int)$intento['numero_intento']; ?></td><td>#<?php echo (int)$intento['id_cuestionario']; ?> (<?php echo $h($intento['estado_version']); ?>)</td><td><?php echo (int)$intento['aciertos']; ?>/5</td><td><strong><?php echo (int)$intento['puntaje']; ?>/100</strong></td><td><?php echo $h(date('d/m/Y H:i', strtotime((string)$intento['fecha_realizacion']))); ?></td></tr>
                        <?php endforeach; ?></tbody>
                    </table></div>
                    <?php if (count($intentos) === 100): ?><p class="vq-report-note">Se muestran los 100 intentos más recientes de esta combinación usuario-video.</p><?php endif; ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</main></div><script src="../../assets/js/admin.js"></script>
</body></html>
