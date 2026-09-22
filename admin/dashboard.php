<?php


require_once "../config/sesion.php";

verificarAdmin();



require_once "../models/Dashboard.php";
require_once "../controllers/InteraccionController.php";


$dashboard = new Dashboard();
$interaccionController = new InteraccionController();
$totalesInteraccion = $interaccionController->totalesGlobales();



// =========================================
// ESTADISTICAS
// =========================================

$totalVideos = $dashboard->totalVideos();

$totalCapitulos = $dashboard->totalCapitulos();

$totalSeries = $dashboard->totalSeries();

$totalTemporadas = $dashboard->totalTemporadas();

$totalCategorias = $dashboard->totalCategorias();

$totalUsuarios = $dashboard->totalUsuarios();

$totalVistas = $dashboard->totalVistas();

$totalPublicados = $dashboard->totalPublicados();

$totalBorradores = $dashboard->totalBorradores();



// =========================================
// INFORMACION DEL DASHBOARD
// =========================================

$videosRecientes = $dashboard->videosRecientes();

$masVistos = $dashboard->masVistos();

$seriesRecientes = $dashboard->seriesRecientes();



?>


<!DOCTYPE html>

<html lang="es">


<head>


<meta charset="UTF-8">


<meta
name="viewport"
content="width=device-width, initial-scale=1.0"
>


<title>
Dashboard - DEVIOZ VIDEOS
</title>


<link
rel="stylesheet"
href="../assets/css/admin.css"
>


</head>


<body>



<?php include "includes/sidebar.php"; ?>



<div class="admin-main">


<?php include "includes/navbar.php"; ?>



<section class="admin-content">



<div class="dashboard-header">


<div>

<h1>
Dashboard
</h1>


<p class="dashboard-subtitle">

Resumen general de DEVIOZ VIDEOS

</p>

</div>


</div>






<!-- =========================================
     TARJETAS PRINCIPALES
========================================= -->


<div class="stats-container dashboard-stats">



<div class="stat-card">


<div class="stat-icon">

🎬

</div>


<h3>
Videos
</h3>


<p>

<?php echo $totalVideos; ?>

</p>


<span class="stat-description">

Videos independientes

</span>


</div>





<div class="stat-card">


<div class="stat-icon">

📺

</div>


<h3>
Capítulos
</h3>


<p>

<?php echo $totalCapitulos; ?>

</p>


<span class="stat-description">

Capítulos de series

</span>


</div>





<div class="stat-card">


<div class="stat-icon">

🎞️

</div>


<h3>
Series
</h3>


<p>

<?php echo $totalSeries; ?>

</p>


<span class="stat-description">

Series registradas

</span>


</div>





<div class="stat-card">


<div class="stat-icon">

📚

</div>


<h3>
Temporadas
</h3>


<p>

<?php echo $totalTemporadas; ?>

</p>


<span class="stat-description">

Temporadas creadas

</span>


</div>





<div class="stat-card">


<div class="stat-icon">

📁

</div>


<h3>
Categorías
</h3>


<p>

<?php echo $totalCategorias; ?>

</p>


<span class="stat-description">

Categorías disponibles

</span>


</div>





<div class="stat-card">


<div class="stat-icon">

👁️

</div>


<h3>
Visualizaciones
</h3>


<p>

<?php echo number_format($totalVistas); ?>

</p>


<span class="stat-description">

Reproducciones acumuladas

</span>


</div>





<?php if(isset($_SESSION["rol"]) && $_SESSION["rol"] === "admin"): ?>


<div class="stat-card">


<div class="stat-icon">

👥

</div>


<h3>
Usuarios
</h3>


<p>

<?php echo $totalUsuarios; ?>

</p>


<span class="stat-description">

Usuarios administrativos

</span>


</div>


<?php endif; ?>





<div class="stat-card">


<div class="stat-icon">

✅

</div>


<h3>
Publicados
</h3>


<p>

<?php echo $totalPublicados; ?>

</p>


<span class="stat-description">

Contenido publicado

</span>


</div>





<div class="stat-card">


<div class="stat-icon">

📝

</div>


<h3>
Borradores
</h3>


<p>

<?php echo $totalBorradores; ?>

</p>


<span class="stat-description">

Contenido pendiente

</span>


</div>





<div class="stat-card">
<div class="stat-icon">♡</div>
<h3>Likes</h3>
<p><?php echo number_format((int)($totalesInteraccion['total_likes'] ?? 0)); ?></p>
<span class="stat-description">Interacciones positivas</span>
</div>

<div class="stat-card">
<div class="stat-icon">☆</div>
<h3>Favoritos</h3>
<p><?php echo number_format((int)($totalesInteraccion['total_favoritos'] ?? 0)); ?></p>
<span class="stat-description">Videos guardados</span>
</div>

<div class="stat-card">
<div class="stat-icon">◌</div>
<h3>Comentarios</h3>
<p><?php echo number_format((int)($totalesInteraccion['total_comentarios'] ?? 0)); ?></p>
<span class="stat-description">Participación de usuarios</span>
</div>
</div>






<!-- =========================================
     CONTENIDO RECIENTE
========================================= -->


<div class="dashboard-section">


<div class="dashboard-section-header">


<div>

<h2>
🕒 Contenido reciente
</h2>


<p>

Últimos videos y capítulos registrados

</p>

</div>


<a
href="videos/listar.php"
class="dashboard-link"
>

Ver todos →

</a>


</div>





<div class="dashboard-table-wrapper">


<table class="dashboard-table">


<thead>


<tr>


<th>
Título
</th>


<th>
Categoría
</th>


<th>
Tipo
</th>


<th>
Estado
</th>


<th>
Fecha
</th>


</tr>


</thead>




<tbody>


<?php if(!empty($videosRecientes)): ?>


<?php foreach($videosRecientes as $video): ?>


<tr>


<td>


<strong>

<?php echo htmlspecialchars($video["titulo"]); ?>

</strong>


</td>



<td>

<?php echo htmlspecialchars($video["categoria"]); ?>

</td>



<td>


<?php if($video["tipo_contenido"] === "serie"): ?>


<span class="tipo-contenido tipo-serie">

Capítulo

</span>


<?php else: ?>


<span class="tipo-contenido tipo-video">

Video

</span>


<?php endif; ?>


</td>



<td>


<?php if($video["estado"] === "publicado"): ?>


<span class="estado publicado">

Publicado

</span>


<?php else: ?>


<span class="estado borrador">

Borrador

</span>


<?php endif; ?>


</td>



<td>

<?php echo $video["fecha_publicacion"]; ?>

</td>


</tr>


<?php endforeach; ?>


<?php else: ?>


<tr>


<td colspan="5" class="tabla-vacia">

No hay contenido registrado.

</td>


</tr>


<?php endif; ?>


</tbody>


</table>


</div>


</div>






<!-- =========================================
     DOS COLUMNAS
========================================= -->


<div class="dashboard-grid">






<!-- MAS VISTOS -->


<div class="dashboard-section">


<div class="dashboard-section-header">


<div>

<h2>
🔥 Más vistos
</h2>


<p>

Contenido con más reproducciones

</p>

</div>


</div>




<div class="ranking-list">


<?php if(!empty($masVistos)): ?>


<?php foreach($masVistos as $index => $video): ?>


<a
href="../public/detalle.php?id=<?php echo $video["id_video"]; ?>"
class="ranking-item"
target="_blank"
>


<div class="ranking-number">

<?php echo $index + 1; ?>

</div>



<div class="ranking-info">


<h3>

<?php echo htmlspecialchars($video["titulo"]); ?>

</h3>


<p>

<?php echo htmlspecialchars($video["categoria"]); ?>

</p>


</div>



<div class="ranking-views">

👁

<?php echo number_format($video["vistas"]); ?>

</div>


</a>


<?php endforeach; ?>


<?php else: ?>


<p class="tabla-vacia">

No hay información de visualizaciones.

</p>


<?php endif; ?>


</div>


</div>







<!-- SERIES RECIENTES -->


<div class="dashboard-section">


<div class="dashboard-section-header">


<div>

<h2>
🎞️ Series recientes
</h2>


<p>

Últimas series creadas

</p>

</div>


<a
href="series/listar.php"
class="dashboard-link"
>

Ver todas →

</a>


</div>




<div class="series-dashboard-list">


<?php if(!empty($seriesRecientes)): ?>


<?php foreach($seriesRecientes as $serie): ?>


<div class="serie-dashboard-item">



<div class="serie-dashboard-poster">


<?php if(!empty($serie["imagen_portada"])): ?>


<img
src="../uploads/series/<?php echo htmlspecialchars($serie["imagen_portada"]); ?>"
alt="<?php echo htmlspecialchars($serie["titulo"]); ?>"
>


<?php else: ?>


<div class="serie-dashboard-sin-imagen">

🎞️

</div>


<?php endif; ?>


</div>




<div class="serie-dashboard-info">


<h3>

<?php echo htmlspecialchars($serie["titulo"]); ?>

</h3>



<?php if($serie["estado"] == 1): ?>


<span class="estado activo">

Activo

</span>


<?php else: ?>


<span class="estado inactivo">

Inactivo

</span>


<?php endif; ?>



<p>

<?php echo $serie["fecha_creacion"]; ?>

</p>


</div>



<a
href="series/editar.php?id=<?php echo $serie["id_serie"]; ?>"
class="dashboard-edit-link"
>

Editar →

</a>


</div>


<?php endforeach; ?>


<?php else: ?>


<p class="tabla-vacia">

No hay series registradas.

</p>


<?php endif; ?>


</div>


</div>



</div>



</section>


</div>






</body>

</html>