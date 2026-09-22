<?php
require_once '../config/sesion.php';
require_once '../controllers/VideoController.php';
require_once '../controllers/InteraccionController.php';

$videoController = new VideoController();
$interaccionController = new InteraccionController();

$buscar = trim((string)($_GET['buscar'] ?? ''));
$categoria = isset($_GET['categoria']) && ctype_digit((string)$_GET['categoria']) ? (string)$_GET['categoria'] : '';
$orden = in_array($_GET['orden'] ?? '', ['recientes','popular','antiguos','titulo'], true) ? $_GET['orden'] : 'recientes';

$videos = $videoController->buscarPublicos($buscar, $categoria, $orden);
$categorias = $videoController->listarCategorias();
$populares = $interaccionController->videosPopulares(8);
$recomendados = usuarioAutenticado()
    ? $interaccionController->recomendadosUsuario((int)$_SESSION['id_usuario'], 8)
    : array_slice($videos, 0, 8);

$vistosRecientemente = [];
$continuarViendo = [];
if(usuarioAutenticado()) {
    $idUsuarioInicio = (int)$_SESSION['id_usuario'];
    $vistosRecientemente = array_slice($interaccionController->historialUsuario($idUsuarioInicio, 12), 0, 8);
    $continuarViendo = $interaccionController->continuarViendoUsuario($idUsuarioInicio, 8);
}

$modoBusqueda = $buscar !== '' || $categoria !== '';
$hero = $populares[0] ?? ($videos[0] ?? null);
?>

<?php include '../includes/public_header.php'; ?>
<?php include '../includes/public_navbar.php'; ?>

<div class="layout">
<?php include '../includes/public_sidebar.php'; ?>

<main class="public-content">
    <?php if(!$modoBusqueda): ?>
    <section class="platform-hero">
        <div class="platform-hero-copy">
            <span class="hero-kicker">Aprende · Explora · Descubre</span>
            <h1>Contenido tecnológico para seguir avanzando.</h1>
            <p>Videos, series y aprendizaje en una experiencia más rápida, clara y personalizada.</p>
            <div class="hero-actions">
                <?php if($hero): ?>
                    <a href="detalle.php?id=<?php echo (int)$hero['id_video']; ?>" class="btn-primary-modern">▶ Reproducir destacado</a>
                <?php else: ?>
                    <a href="#contenido" class="btn-primary-modern">Explorar plataforma</a>
                <?php endif; ?>
                <a href="series.php" class="btn-secondary-modern">Ver series</a>
                <?php if(usuarioAutenticado()): ?><a href="aprendizaje.php" class="btn-secondary-modern">🎓 Learning Lab</a><?php endif; ?>
            </div>
        </div>
        <div class="hero-visual" aria-hidden="true">
            <div class="hero-orbit hero-orbit-one"></div>
            <div class="hero-orbit hero-orbit-two"></div>
            <div class="hero-tech-card"><strong>DEVIOZ</strong><span>LEARNING</span><small>Videos · Cursos · Tech</small></div>
        </div>
    </section>

    <?php if(!empty($categorias)): ?>
    <div class="category-strip" aria-label="Categorías">
        <?php foreach(array_slice($categorias, 0, 10) as $cat): ?>
            <a href="index.php?categoria=<?php echo (int)$cat['id_categoria']; ?>"><?php echo htmlspecialchars($cat['nombre']); ?></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <section class="content-section" id="contenido">
        <div class="section-heading-row">
            <div>
                <span class="section-kicker"><?php echo $modoBusqueda ? 'Resultados' : 'Últimos contenidos'; ?></span>
                <h2><?php echo $modoBusqueda ? 'Explora los resultados' : 'Videos subidos recientemente'; ?></h2>
                <?php if($buscar !== ''): ?><p>Resultados para “<?php echo htmlspecialchars($buscar); ?>”.</p><?php endif; ?>
            </div>
            <form class="sort-form" method="GET">
                <?php if($buscar !== ''): ?><input type="hidden" name="buscar" value="<?php echo htmlspecialchars($buscar); ?>"><?php endif; ?>
                <?php if($categoria !== ''): ?><input type="hidden" name="categoria" value="<?php echo htmlspecialchars($categoria); ?>"><?php endif; ?>
                <label for="orden">Ordenar</label>
                <select id="orden" name="orden" onchange="this.form.submit()">
                    <option value="recientes" <?php echo $orden === 'recientes' ? 'selected' : ''; ?>>Más recientes</option>
                    <option value="popular" <?php echo $orden === 'popular' ? 'selected' : ''; ?>>Más vistos</option>
                    <option value="antiguos" <?php echo $orden === 'antiguos' ? 'selected' : ''; ?>>Más antiguos</option>
                    <option value="titulo" <?php echo $orden === 'titulo' ? 'selected' : ''; ?>>Título A-Z</option>
                </select>
            </form>
        </div>

        <?php if(empty($videos)): ?>
            <div class="empty-state">
                <span class="empty-icon">⌕</span>
                <h3>No encontramos contenido</h3>
                <p><?php echo $modoBusqueda ? 'Prueba con otra búsqueda o categoría.' : 'Aún no hay videos disponibles. El administrador puede cargar el primer contenido.'; ?></p>
                <?php if($modoBusqueda): ?><a href="index.php" class="btn-secondary-modern">Limpiar filtros</a><?php endif; ?>
            </div>
        <?php else: ?>
            <div class="grid-videos">
                <?php foreach($videos as $cardVideo): include '../includes/video_card.php'; endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php if(!$modoBusqueda && usuarioAutenticado() && !empty($continuarViendo)): ?>
    <section class="content-section home-personal-section">
        <div class="section-heading-row">
            <div>
                <span class="section-kicker">Retoma donde lo dejaste</span>
                <h2>Continuar viendo</h2>
                <p>Videos que dejaste a medias, ordenados por tu actividad más reciente.</p>
            </div>
            <a href="historial.php" class="section-inline-link">Ver historial →</a>
        </div>
        <div class="grid-videos grid-videos-compact">
            <?php foreach($continuarViendo as $cardVideo): include '../includes/video_card.php'; endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if(!$modoBusqueda && usuarioAutenticado() && !empty($vistosRecientemente)): ?>
    <section class="content-section home-personal-section">
        <div class="section-heading-row">
            <div>
                <span class="section-kicker">Tu actividad</span>
                <h2>Vistos recientemente</h2>
                <p>Accede rápidamente a los contenidos que viste hace poco.</p>
            </div>
            <a href="historial.php" class="section-inline-link">Ver todos →</a>
        </div>
        <div class="grid-videos grid-videos-compact">
            <?php foreach($vistosRecientemente as $cardVideo): include '../includes/video_card.php'; endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if(!$modoBusqueda && !empty($populares)): ?>
    <section class="content-section">
        <div class="section-heading-row"><div><span class="section-kicker">Tendencias</span><h2>Más populares</h2><p>Ordenados con datos reales de visualizaciones e interacción.</p></div></div>
        <div class="grid-videos grid-videos-compact">
            <?php foreach($populares as $cardVideo): include '../includes/video_card.php'; endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if(!$modoBusqueda && !empty($recomendados)): ?>
    <section class="content-section">
        <div class="section-heading-row"><div><span class="section-kicker">Para ti</span><h2><?php echo usuarioAutenticado() ? 'Recomendados según tu actividad' : 'Contenido destacado'; ?></h2><p><?php echo usuarioAutenticado() ? 'Usamos categorías de tu historial, likes y favoritos; si faltan datos mostramos contenido popular.' : 'Una selección basada en contenido reciente y popular.'; ?></p></div></div>
        <div class="grid-videos grid-videos-compact">
            <?php foreach($recomendados as $cardVideo): include '../includes/video_card.php'; endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</main>
</div>

<?php include '../includes/public_footer.php'; ?>
