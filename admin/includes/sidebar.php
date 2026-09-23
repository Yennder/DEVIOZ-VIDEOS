<?php

$rutaActual = $_SERVER["REQUEST_URI"];

function menuActivo($textoRuta)
{
    global $rutaActual;

    return strpos($rutaActual, $textoRuta) !== false
        ? "activo-menu"
        : "";
}

?>


<aside class="admin-sidebar">


<?php

require_once __DIR__ . "/../../controllers/ConfiguracionController.php";

$configController = new ConfiguracionController();

$logoSitio = $configController->obtener("logo_sitio");

$logoPath = $logoSitio
    ? __DIR__ . "/../../uploads/config/" . basename($logoSitio)
    : null;

$logoDisponible = $logoPath && is_file($logoPath);

?>

<div class="admin-logo">

<a href="/DEVIOZ-VIDEOS/admin/dashboard.php" aria-label="DEVIOZ VIDEOS - Dashboard">

    <?php if($logoDisponible): ?>
        <img
        src="/DEVIOZ-VIDEOS/uploads/config/<?php echo htmlspecialchars(basename($logoSitio)); ?>"
        alt="DEVIOZ VIDEOS"
        class="admin-logo-img"
        >
    <?php else: ?>
        <span class="admin-logo-fallback">
            <span class="mark">DV</span>
            <span>DEVIOZ<small>VIDEOS ADMIN</small></span>
        </span>
    <?php endif; ?>

</a>

</div>



<nav>


<a
href="/DEVIOZ-VIDEOS/admin/dashboard.php"
class="<?php echo menuActivo('/admin/dashboard.php'); ?>"
>

📊 Dashboard

</a>



<a
href="/DEVIOZ-VIDEOS/admin/videos/listar.php"
class="<?php echo menuActivo('/admin/videos/'); ?>"
>

🎬 Videos

</a>



<a
href="/DEVIOZ-VIDEOS/admin/series/listar.php"
class="<?php echo menuActivo('/admin/series/'); ?>"
>

📺 Series

</a>



<a
href="/DEVIOZ-VIDEOS/admin/temporadas/listar.php"
class="<?php echo menuActivo('/admin/temporadas/'); ?>"
>

📚 Temporadas

</a>



<a
href="/DEVIOZ-VIDEOS/admin/categorias/listar.php"
class="<?php echo menuActivo('/admin/categorias/'); ?>"
>

📁 Categorías

</a>

<a
href="/DEVIOZ-VIDEOS/admin/comentarios/listar.php"
class="<?php echo menuActivo('/admin/comentarios/'); ?>"
>

💬 Comentarios

</a>

<div class="admin-nav-label">INTELIGENCIA DEL CONTENIDO</div>

<a
href="/DEVIOZ-VIDEOS/admin/transcripciones/listar.php"
class="<?php echo menuActivo('/admin/transcripciones/'); ?>"
>

&#128483; Transcripciones

</a>

<div class="admin-nav-label">TECHFLIX LEARNING LAB</div>

<a
href="/DEVIOZ-VIDEOS/admin/learning/index.php"
class="<?php echo menuActivo('/admin/learning/index.php'); ?>"
>
🎓 Learning Lab
</a>

<a
href="/DEVIOZ-VIDEOS/admin/learning/cursos/listar.php"
class="<?php echo menuActivo('/admin/learning/cursos/'); ?>"
>
📘 Cursos
</a>

<a
href="/DEVIOZ-VIDEOS/admin/learning/capacitaciones/listar.php"
class="<?php echo menuActivo('/admin/learning/capacitaciones/'); ?>"
>
🗓️ Capacitaciones
</a>

<a
href="/DEVIOZ-VIDEOS/admin/learning/evaluaciones/listar.php"
class="<?php echo menuActivo('/admin/learning/evaluaciones/'); ?>"
>
📝 Evaluaciones
</a>

<a
href="/DEVIOZ-VIDEOS/admin/learning/logros/listar.php"
class="<?php echo menuActivo('/admin/learning/logros/'); ?>"
>
🏅 Logros
</a>

<a
href="/DEVIOZ-VIDEOS/admin/learning/seguimiento.php"
class="<?php echo menuActivo('/admin/learning/seguimiento.php'); ?>"
>
📈 Seguimiento
</a>

<a
href="/DEVIOZ-VIDEOS/admin/learning/certificados.php"
class="<?php echo menuActivo('/admin/learning/certificados.php'); ?>"
>
🎓 Certificados
</a>



<?php if(isset($_SESSION["rol"]) && $_SESSION["rol"] === "admin"): ?>

<a
href="/DEVIOZ-VIDEOS/admin/usuarios/listar.php"
class="<?php echo menuActivo('/admin/usuarios/'); ?>"
>

👥 Usuarios

</a>

<?php endif; ?>


<?php if(isset($_SESSION["rol"]) && $_SESSION["rol"] === "admin"): ?>

<a href="/DEVIOZ-VIDEOS/admin/configuracion/index.php">

    ⚙️ Configuración

</a>

<?php endif; ?>


<a href="/DEVIOZ-VIDEOS/logout.php">

🚪 Cerrar sesión

</a>



</nav>



</aside>