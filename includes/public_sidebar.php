<?php
$paginaActual = basename($_SERVER['PHP_SELF'] ?? 'index.php');
$categoriaActual = isset($_GET['categoria']) ? (string)$_GET['categoria'] : null;
$categorias = $categorias ?? [];
$generoActual = isset($_GET['genero']) && ctype_digit((string)$_GET['genero']) ? (string)$_GET['genero'] : '';
if (!isset($generosPublicos)) {
    require_once __DIR__ . '/../models/Genero.php';
    try { $generosPublicos = (new Genero())->listar(true); }
    catch (Throwable $e) { $generosPublicos = []; }
}
?>

<aside class="public-sidebar" id="publicSidebar">
    <div class="sidebar-section">
        <span class="sidebar-eyebrow">Explorar</span>
        <a href="index.php" class="<?php echo $paginaActual === 'index.php' && empty($categoriaActual) && $generoActual === '' ? 'menu-publico-activo' : ''; ?>"><span>⌂</span>Inicio</a>
    </div>

    <?php if(usuarioAutenticado()): ?>
    <div class="sidebar-section">
        <?php
        $learningAbierto = in_array($paginaActual, [
            'aprendizaje.php', 'curso.php', 'evaluacion.php', 'progreso.php',
            'skills.php', 'logros.php', 'certificados.php', 'notificaciones.php',
            'solicitudes_descarga.php', 'solicitud_descarga.php'
        ], true);
        ?>
        <details class="learning-sidebar-nav" <?php echo $learningAbierto ? 'open' : ''; ?>>
            <summary class="sidebar-eyebrow learning-sidebar-summary">
                <span>Learning Lab</span>
                <span class="learning-sidebar-chevron" aria-hidden="true"></span>
            </summary>
            <div class="learning-sidebar-list">
                <a href="aprendizaje.php" class="<?php echo in_array($paginaActual, ['aprendizaje.php','curso.php','evaluacion.php'], true) ? 'menu-publico-activo' : ''; ?>"><span>🎓</span>Mi aprendizaje</a>
                <a href="progreso.php" class="<?php echo $paginaActual === 'progreso.php' ? 'menu-publico-activo' : ''; ?>"><span>↗</span>Mi progreso</a>
                <a href="skills.php" class="<?php echo $paginaActual === 'skills.php' ? 'menu-publico-activo' : ''; ?>"><span>🧩</span>Mis skills</a>
                <a href="logros.php" class="<?php echo $paginaActual === 'logros.php' ? 'menu-publico-activo' : ''; ?>"><span>🏅</span>Mis logros</a>
                <a href="certificados.php" class="<?php echo $paginaActual === 'certificados.php' ? 'menu-publico-activo' : ''; ?>"><span>🎓</span>Mis certificados</a>
                <a href="notificaciones.php" class="<?php echo $paginaActual === 'notificaciones.php' ? 'menu-publico-activo' : ''; ?>"><span>🔔</span>Notificaciones</a>
                <a href="solicitudes_descarga.php" class="<?php echo in_array($paginaActual, ['solicitudes_descarga.php','solicitud_descarga.php'], true) ? 'menu-publico-activo' : ''; ?>"><span>🔐</span>Mis solicitudes</a>
            </div>
        </details>
    </div>
    <div class="sidebar-section">
        <span class="sidebar-eyebrow">Mi biblioteca</span>
        <a href="favoritos.php" class="<?php echo $paginaActual === 'favoritos.php' ? 'menu-publico-activo' : ''; ?>"><span>♡</span>Favoritos</a>
        <a href="historial.php" class="<?php echo $paginaActual === 'historial.php' ? 'menu-publico-activo' : ''; ?>"><span>◷</span>Historial</a>
        <a href="mis_cuestionarios.php" class="<?php echo $paginaActual === 'mis_cuestionarios.php' ? 'menu-publico-activo' : ''; ?>"><span>📝</span>Mis cuestionarios</a>
        <a href="mis_escenas.php" class="<?php echo $paginaActual === 'mis_escenas.php' ? 'menu-publico-activo' : ''; ?>"><span>◈</span>Mis escenas</a>
        <a href="playlists.php" class="<?php echo $paginaActual === 'playlists.php' ? 'menu-publico-activo' : ''; ?>"><span>☷</span>Playlists</a>
    </div>
    <?php endif; ?>

    <div class="sidebar-section">
        <span class="sidebar-eyebrow">Categorías</span>
        <?php if(empty($categorias)): ?>
            <p class="sidebar-empty">Aún no hay categorías.</p>
        <?php else: ?>
            <?php foreach($categorias as $cat): ?>
                <?php
                    $esCategoriaSerie = $cat['nombre'] === 'Serie';
                    $urlCategoria = $esCategoriaSerie ? 'series.php' : 'index.php?categoria=' . (int)$cat['id_categoria'];
                    $activaCategoria = $esCategoriaSerie
                        ? in_array($paginaActual, ['series.php','detalle_serie.php'], true)
                        : $paginaActual === 'index.php' && $categoriaActual === (string)$cat['id_categoria'];
                ?>
                <a href="<?php echo $urlCategoria; ?>" class="<?php echo $activaCategoria ? 'menu-publico-activo' : ''; ?>">
                    <span>◇</span><?php echo htmlspecialchars($cat['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                </a>
                <?php if ($cat['nombre'] === 'Educacional'): ?>
                <a class="genre-course-side-link" href="<?php echo usuarioAutenticado() ? 'aprendizaje.php' : '../views/login.php'; ?>"><span>🎓</span>Explorar cursos</a>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php if (!empty($generosPublicos)): ?>
    <div class="sidebar-section">
        <span class="sidebar-eyebrow">Géneros</span>
        <div class="genre-sidebar-list">
                <?php foreach ($generosPublicos as $gen): ?>
                <a href="index.php?genero=<?php echo (int)$gen['id_genero']; ?>" class="<?php echo $paginaActual === 'index.php' && $generoActual === (string)$gen['id_genero'] ? 'menu-publico-activo' : ''; ?>">
                    <span>◇</span><?php echo htmlspecialchars($gen['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                </a>
                <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</aside>
