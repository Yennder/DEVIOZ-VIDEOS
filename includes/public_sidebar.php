<?php
$paginaActual = basename($_SERVER['PHP_SELF'] ?? 'index.php');
$categoriaActual = isset($_GET['categoria']) ? (string)$_GET['categoria'] : null;
$categorias = $categorias ?? [];
?>

<aside class="public-sidebar" id="publicSidebar">
    <div class="sidebar-section">
        <span class="sidebar-eyebrow">Explorar</span>
        <a href="index.php" class="<?php echo $paginaActual === 'index.php' && empty($categoriaActual) ? 'menu-publico-activo' : ''; ?>"><span>⌂</span>Inicio</a>
        <a href="series.php" class="<?php echo in_array($paginaActual, ['series.php','detalle_serie.php'], true) ? 'menu-publico-activo' : ''; ?>"><span>▣</span>Series</a>
    </div>

    <?php if(usuarioAutenticado()): ?>
    <div class="sidebar-section">
        <span class="sidebar-eyebrow">Learning Lab</span>
        <a href="aprendizaje.php" class="<?php echo in_array($paginaActual, ['aprendizaje.php','curso.php','evaluacion.php'], true) ? 'menu-publico-activo' : ''; ?>"><span>🎓</span>Mi aprendizaje</a>
        <a href="progreso.php" class="<?php echo $paginaActual === 'progreso.php' ? 'menu-publico-activo' : ''; ?>"><span>↗</span>Mi progreso</a>
        <a href="logros.php" class="<?php echo $paginaActual === 'logros.php' ? 'menu-publico-activo' : ''; ?>"><span>🏅</span>Mis logros</a>
        <a href="certificados.php" class="<?php echo $paginaActual === 'certificados.php' ? 'menu-publico-activo' : ''; ?>"><span>🎓</span>Mis certificados</a>
        <a href="notificaciones.php" class="<?php echo $paginaActual === 'notificaciones.php' ? 'menu-publico-activo' : ''; ?>"><span>🔔</span>Notificaciones</a>
    </div>
    <div class="sidebar-section">
        <span class="sidebar-eyebrow">Mi biblioteca</span>
        <a href="favoritos.php" class="<?php echo $paginaActual === 'favoritos.php' ? 'menu-publico-activo' : ''; ?>"><span>♡</span>Favoritos</a>
        <a href="historial.php" class="<?php echo $paginaActual === 'historial.php' ? 'menu-publico-activo' : ''; ?>"><span>◷</span>Historial</a>
        <a href="playlists.php" class="<?php echo $paginaActual === 'playlists.php' ? 'menu-publico-activo' : ''; ?>"><span>☷</span>Playlists</a>
    </div>
    <?php endif; ?>

    <div class="sidebar-section">
        <span class="sidebar-eyebrow">Categorías</span>
        <?php if(empty($categorias)): ?>
            <p class="sidebar-empty">Aún no hay categorías.</p>
        <?php else: ?>
            <?php foreach($categorias as $cat): ?>
                <a href="index.php?categoria=<?php echo (int)$cat['id_categoria']; ?>" class="<?php echo $paginaActual === 'index.php' && $categoriaActual === (string)$cat['id_categoria'] ? 'menu-publico-activo' : ''; ?>">
                    <span>◇</span><?php echo htmlspecialchars($cat['nombre']); ?>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</aside>
