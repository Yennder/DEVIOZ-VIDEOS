<?php
require_once __DIR__ . '/../config/sesion.php';
verificarSesion();
require_once __DIR__ . '/../models/CuestionarioVideoReportes.php';
require_once __DIR__ . '/../controllers/VideoController.php';

$h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$idUsuario = (int)$_SESSION['id_usuario'];
$paginaHechos = min(100000, max(1, filter_var($_GET['hechos'] ?? 1, FILTER_VALIDATE_INT) ?: 1));
$paginaPendientes = min(100000, max(1, filter_var($_GET['pendientes'] ?? 1, FILTER_VALIDATE_INT) ?: 1));
$porPagina = 12;
$error = false;
$totalHechos = $totalPendientes = 0;
$hechos = $pendientes = [];
try {
    $reportes = new CuestionarioVideoReportes();
    if ($reportes->instalado()) {
        $totalHechos = $reportes->contarRespondidos($idUsuario);
        $totalPendientes = $reportes->contarPendientes($idUsuario);
        $paginaHechos = min($paginaHechos, max(1, (int)ceil($totalHechos / $porPagina)));
        $paginaPendientes = min($paginaPendientes, max(1, (int)ceil($totalPendientes / $porPagina)));
        $hechos = $reportes->respondidos($idUsuario, $porPagina, ($paginaHechos - 1) * $porPagina);
        $pendientes = $reportes->pendientes($idUsuario, $porPagina, ($paginaPendientes - 1) * $porPagina);
    } else {
        $error = true;
    }
} catch (Throwable $e) {
    error_log('DEVIOZ V4.5.4.1 - Mis cuestionarios: ' . $e->getMessage());
    $error = true;
}
$categorias = (new VideoController())->listarCategorias();
$paginaEnlace = static function (string $tipo, int $pagina) use ($paginaHechos, $paginaPendientes): string {
    return 'mis_cuestionarios.php?' . http_build_query([
        'hechos' => $tipo === 'hechos' ? $pagina : $paginaHechos,
        'pendientes' => $tipo === 'pendientes' ? $pagina : $paginaPendientes,
    ]) . '#' . ($tipo === 'hechos' ? 'respondidos' : 'pendientes');
};
?>
<?php include __DIR__ . '/../includes/public_header.php'; ?>
<?php include __DIR__ . '/../includes/public_navbar.php'; ?>
<div class="layout">
<?php include __DIR__ . '/../includes/public_sidebar.php'; ?>
<main class="public-content vq-report-public">
    <section class="vq-public-hero">
        <span class="vq-eyebrow">Mi aprendizaje en videos · V4.5.4.1</span>
        <h1>Mis cuestionarios</h1>
        <p>Consulta los videos cuyas cinco preguntas ya respondiste, tu mejor nota y los retos que siguen pendientes. Los intentos no afectan las evaluaciones de Learning Lab.</p>
    </section>
    <?php if ($error): ?>
        <div role="alert" class="vq-alert is-error">No fue posible cargar tus cuestionarios. Comprueba que la migración V4.5.4 esté instalada.</div>
    <?php else: ?>
        <div class="vq-report-stats" aria-label="Resumen de cuestionarios">
            <article><span>Videos evaluados</span><strong><?php echo $totalHechos; ?></strong><small>Con uno o más intentos registrados</small></article>
            <article><span>Cuestionarios pendientes</span><strong><?php echo $totalPendientes; ?></strong><small>Publicados y aún sin responder</small></article>
        </div>
        <section class="vq-report-section" id="respondidos">
            <div class="vq-report-heading"><div><span class="vq-eyebrow">Tu historial</span><h2>Videos que ya evaluaste</h2></div><span class="vq-score-pill"><?php echo $totalHechos; ?> respondidos</span></div>
            <?php if (!$hechos): ?>
                <div class="vq-public-card vq-report-empty"><h3>Aún no has respondido ningún cuestionario</h3><p>Al completar las cinco preguntas de un video, aparecerá aquí con tus notas.</p><a class="vq-secondary" href="index.php">Explorar videos</a></div>
            <?php else: ?>
                <div class="vq-report-grid">
                    <?php foreach ($hechos as $fila): ?>
                    <article class="vq-public-card vq-report-item">
                        <span class="vq-status-done">✓ Respondido</span>
                        <h3><a href="detalle.php?id=<?php echo (int)$fila['id_video']; ?>"><?php echo $h($fila['titulo']); ?></a></h3>
                        <div class="vq-report-metrics"><span>Mejor nota <strong><?php echo (int)$fila['mejor_puntaje']; ?>/100</strong></span><span>Última nota <strong><?php echo (int)$fila['ultima_nota']; ?>/100</strong></span><span>Intentos <strong><?php echo (int)$fila['intentos']; ?></strong></span></div>
                        <small>Última evaluación: <?php echo $h(date('d/m/Y H:i', strtotime((string)$fila['ultima_fecha']))); ?></small>
                        <div class="vq-report-actions"><a class="vq-secondary" href="cuestionario_video.php?id=<?php echo (int)$fila['id_video']; ?>&amp;intento=<?php echo (int)$fila['ultimo_id']; ?>">Ver última nota</a><?php if ($fila['version_publicada']): ?><a class="vq-primary" href="cuestionario_video.php?id=<?php echo (int)$fila['id_video']; ?>">Reintentar</a><?php endif; ?></div>
                    </article>
                    <?php endforeach; ?>
                </div>
                <?php if ($totalHechos > $porPagina): ?>
                    <nav class="vq-pagination" aria-label="Páginas de cuestionarios respondidos">
                        <?php if ($paginaHechos > 1): ?><a href="<?php echo $h($paginaEnlace('hechos', $paginaHechos-1)); ?>">← Anterior</a><?php endif; ?>
                        <span>Página <?php echo $paginaHechos; ?> de <?php echo (int)ceil($totalHechos / $porPagina); ?></span>
                        <?php if ($paginaHechos * $porPagina < $totalHechos): ?><a href="<?php echo $h($paginaEnlace('hechos', $paginaHechos+1)); ?>">Siguiente →</a><?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </section>
        <section class="vq-report-section" id="pendientes">
            <div class="vq-report-heading"><div><span class="vq-eyebrow">Para seguir aprendiendo</span><h2>Cuestionarios pendientes</h2></div><span class="vq-score-pill"><?php echo $totalPendientes; ?> pendientes</span></div>
            <?php if (!$pendientes): ?>
                <div class="vq-public-card vq-report-empty"><h3>No tienes cuestionarios pendientes</h3><p>Cuando se publiquen nuevas evaluaciones de videos que aún no respondiste, aparecerán aquí.</p></div>
            <?php else: ?>
                <div class="vq-report-grid">
                    <?php foreach ($pendientes as $fila): ?>
                    <article class="vq-public-card vq-report-item">
                        <span class="vq-status-pending">● Por responder</span>
                        <h3><a href="detalle.php?id=<?php echo (int)$fila['id_video']; ?>"><?php echo $h($fila['titulo']); ?></a></h3>
                        <p>Cinco preguntas · hasta 100 puntos</p>
                        <div class="vq-report-actions"><a class="vq-secondary" href="detalle.php?id=<?php echo (int)$fila['id_video']; ?>">Ver video</a><a class="vq-primary" href="cuestionario_video.php?id=<?php echo (int)$fila['id_video']; ?>">Responder</a></div>
                    </article>
                    <?php endforeach; ?>
                </div>
                <?php if ($totalPendientes > $porPagina): ?>
                    <nav class="vq-pagination" aria-label="Páginas de cuestionarios pendientes">
                        <?php if ($paginaPendientes > 1): ?><a href="<?php echo $h($paginaEnlace('pendientes', $paginaPendientes-1)); ?>">← Anterior</a><?php endif; ?>
                        <span>Página <?php echo $paginaPendientes; ?> de <?php echo (int)ceil($totalPendientes / $porPagina); ?></span>
                        <?php if ($paginaPendientes * $porPagina < $totalPendientes): ?><a href="<?php echo $h($paginaEnlace('pendientes', $paginaPendientes+1)); ?>">Siguiente →</a><?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main></div>
<?php include __DIR__ . '/../includes/public_footer.php'; ?>
