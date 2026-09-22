<?php
require_once '../config/sesion.php';
require_once '../controllers/SerieController.php';
require_once '../controllers/VideoController.php';

$serieController = new SerieController();
$videoController = new VideoController();

$series = $serieController->listarPublicas();
$categorias = $videoController->listarCategorias();
?>
<?php include '../includes/public_header.php'; ?>
<?php include '../includes/public_navbar.php'; ?>

<div class="layout">
<?php include '../includes/public_sidebar.php'; ?>
<main class="public-content">
    <section class="page-hero-compact">
        <span class="section-kicker">Colecciones</span>
        <h1>Series</h1>
        <p>Explora contenido organizado por temporadas y capítulos.</p>
    </section>

    <section class="content-section content-section-first">
        <?php if(empty($series)): ?>
            <div class="empty-state">
                <span class="empty-icon">▣</span>
                <h3>Aún no hay series disponibles</h3>
                <p>Cuando el administrador publique una serie aparecerá en esta sección.</p>
                <a href="index.php" class="btn-secondary-modern">Volver al inicio</a>
            </div>
        <?php else: ?>
            <div class="grid-videos">
                <?php foreach($series as $serie): ?>
                    <?php
                    $portada = !empty($serie['imagen_portada']) ? basename((string)$serie['imagen_portada']) : '';
                    $portadaPath = $portada !== '' ? (__DIR__ . '/../uploads/series/' . $portada) : '';
                    ?>
                    <article class="card-video series-card-modern">
                        <a class="thumbnail" href="detalle_serie.php?id=<?php echo (int)$serie['id_serie']; ?>">
                            <?php if($portadaPath !== '' && is_file($portadaPath)): ?>
                                <img src="../uploads/series/<?php echo htmlspecialchars($portada); ?>" alt="Portada de <?php echo htmlspecialchars($serie['titulo']); ?>" loading="lazy">
                            <?php else: ?>
                                <span class="thumbnail-placeholder"><span>DV</span><small>SERIE</small></span>
                            <?php endif; ?>
                            <span class="card-play">▶</span>
                        </a>
                        <div class="video-info">
                            <div class="video-card-topline"><span class="video-type-badge">SERIE</span></div>
                            <h3><a href="detalle_serie.php?id=<?php echo (int)$serie['id_serie']; ?>"><?php echo htmlspecialchars($serie['titulo']); ?></a></h3>
                            <p class="video-card-description"><?php echo htmlspecialchars((string)($serie['descripcion'] ?? 'Explora sus temporadas y capítulos.')); ?></p>
                            <a class="btn-video" href="detalle_serie.php?id=<?php echo (int)$serie['id_serie']; ?>">Ver serie →</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
</div>

<?php include '../includes/public_footer.php'; ?>
