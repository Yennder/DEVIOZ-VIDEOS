<?php
require_once '../config/sesion.php';
verificarSesion();
require_once '../controllers/VideoController.php';
require_once '../controllers/InteraccionController.php';

$videoController = new VideoController();
$interaccionController = new InteraccionController();
$categorias = $videoController->listarCategorias();
$videos = $interaccionController->favoritosUsuario((int)$_SESSION['id_usuario']);
?>
<?php include '../includes/public_header.php'; ?>
<?php include '../includes/public_navbar.php'; ?>
<div class="layout">
<?php include '../includes/public_sidebar.php'; ?>
<main class="public-content">
    <section class="page-hero-compact">
        <span class="section-kicker">Mi biblioteca</span>
        <h1>Favoritos</h1>
        <p>Tu colección personal de videos guardados para volver cuando quieras.</p>
    </section>
    <section class="content-section content-section-first">
        <?php if(empty($videos)): ?>
            <div class="empty-state"><span class="empty-icon">♡</span><h3>Todavía no tienes videos favoritos</h3><p>Abre cualquier video y usa el botón “Favoritos” para agregarlo aquí.</p><a href="index.php" class="btn-primary-modern">Explorar videos</a></div>
        <?php else: ?>
            <div class="grid-videos">
                <?php foreach($videos as $cardVideo): include '../includes/video_card.php'; endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
</div>
<?php include '../includes/public_footer.php'; ?>
