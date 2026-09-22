<?php
require_once '../config/sesion.php';
verificarSesion();
require_once '../controllers/VideoController.php';
require_once '../controllers/InteraccionController.php';

$videoController = new VideoController();
$interaccionController = new InteraccionController();
$categorias = $videoController->listarCategorias();
$videos = $interaccionController->historialUsuario((int)$_SESSION['id_usuario'], 80);
?>
<?php include '../includes/public_header.php'; ?>
<?php include '../includes/public_navbar.php'; ?>
<div class="layout">
<?php include '../includes/public_sidebar.php'; ?>
<main class="public-content">
    <section class="page-hero-compact">
        <span class="section-kicker">Actividad reciente</span>
        <h1>Mi historial</h1>
        <p>Continúa viendo tus contenidos desde el punto aproximado donde los dejaste.</p>
    </section>
    <section class="content-section content-section-first">
        <?php if(empty($videos)): ?>
            <div class="empty-state"><span class="empty-icon">◷</span><h3>Aún no tienes historial de reproducción</h3><p>Los videos que abras aparecerán aquí automáticamente.</p><a href="index.php" class="btn-primary-modern">Ver contenido</a></div>
        <?php else: ?>
            <div class="grid-videos">
                <?php foreach($videos as $cardVideo): include '../includes/video_card.php'; endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
</div>
<?php include '../includes/public_footer.php'; ?>
