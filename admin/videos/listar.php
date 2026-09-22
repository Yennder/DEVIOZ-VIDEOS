<?php


require_once "../../config/sesion.php";

verificarAdmin();



require_once "../../controllers/VideoController.php";


$videoController = new VideoController();



// =========================================
// FILTROS
// =========================================

$buscar = trim($_GET["buscar"] ?? "");

$categoria = $_GET["categoria"] ?? "";

$tipo = $_GET["tipo"] ?? "";

$estado = $_GET["estado"] ?? "";

$orden = $_GET["orden"] ?? "recientes";



$videos = $videoController->listarFiltrado(
    $buscar,
    $categoria,
    $tipo,
    $estado,
    $orden
);



$categorias = $videoController->categorias();



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
Videos - DEVIOZ VIDEOS
</title>


<link
rel="stylesheet"
href="../../assets/css/admin.css"
>


</head>


<body>



<?php include "../includes/sidebar.php"; ?>



<div class="admin-main">



<?php include "../includes/navbar.php"; ?>



<section class="admin-content">



<div class="gestion-header">


<div>


<h1>
Gestión de Videos
</h1>


<p>
Administra videos independientes y capítulos de series.
</p>


</div>



<a
href="crear.php"
class="btn"
>

+ Nuevo Video

</a>


</div>





<!-- =========================================
     FILTROS
========================================= -->


<form
method="GET"
class="filtros-admin"
>


<div class="filtro-busqueda">


<label>
Buscar
</label>


<input
type="text"
name="buscar"
placeholder="Buscar por título..."
value="<?php echo htmlspecialchars($buscar); ?>"
>


</div>




<div>


<label>
Categoría
</label>


<select name="categoria">


<option value="">
Todas
</option>


<?php foreach($categorias as $cat): ?>


<option
value="<?php echo $cat["id_categoria"]; ?>"
<?php echo $categoria == $cat["id_categoria"] ? "selected" : ""; ?>
>

<?php echo htmlspecialchars($cat["nombre"]); ?>

</option>


<?php endforeach; ?>


</select>


</div>




<div>


<label>
Tipo
</label>


<select name="tipo">


<option value="">
Todos
</option>


<option
value="video"
<?php echo $tipo === "video" ? "selected" : ""; ?>
>

Video independiente

</option>


<option
value="serie"
<?php echo $tipo === "serie" ? "selected" : ""; ?>
>

Capítulo de serie

</option>


</select>


</div>




<div>


<label>
Estado
</label>


<select name="estado">


<option value="">
Todos
</option>


<option
value="publicado"
<?php echo $estado === "publicado" ? "selected" : ""; ?>
>

Publicado

</option>


<option
value="borrador"
<?php echo $estado === "borrador" ? "selected" : ""; ?>
>

Borrador

</option>


</select>


</div>




<div>


<label>
Orden
</label>


<select name="orden">


<option
value="recientes"
<?php echo $orden === "recientes" ? "selected" : ""; ?>
>

Más recientes

</option>


<option
value="antiguos"
<?php echo $orden === "antiguos" ? "selected" : ""; ?>
>

Más antiguos

</option>


<option
value="mas_vistos"
<?php echo $orden === "mas_vistos" ? "selected" : ""; ?>
>

Más vistos

</option>


<option
value="menos_vistos"
<?php echo $orden === "menos_vistos" ? "selected" : ""; ?>
>

Menos vistos

</option>


<option
value="titulo_az"
<?php echo $orden === "titulo_az" ? "selected" : ""; ?>
>

Título A-Z

</option>


<option
value="titulo_za"
<?php echo $orden === "titulo_za" ? "selected" : ""; ?>
>

Título Z-A

</option>


</select>


</div>




<div class="filtros-botones">


<button
type="submit"
class="btn-filtrar"
>

🔎 Filtrar

</button>


<a
href="listar.php"
class="btn-limpiar"
>

Limpiar

</a>


</div>


</form>





<!-- =========================================
     RESULTADOS
========================================= -->


<div class="resultado-filtros">


<span>

<?php echo count($videos); ?>

resultado<?php echo count($videos) != 1 ? "s" : ""; ?>

</span>


</div>





<table class="admin-table videos-table">


<thead>


<tr>


<th>
ID
</th>


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
Usuario
</th>


<th>
Vistas
</th>


<th>
Estado
</th>


<th>
Fecha
</th>


<th>
Acciones
</th>


</tr>


</thead>




<tbody>



<?php if(!empty($videos)): ?>


<?php foreach($videos as $video): ?>


<tr>



<td>

<?php echo $video["id_video"]; ?>

</td>




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

<?php echo htmlspecialchars($video["usuario"]); ?>

</td>




<td>

👁 <?php echo $video["vistas"]; ?>

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




<td class="acciones">


<div class="acciones-contenedor">


<a
class="btn-editar"
href="editar.php?id=<?php echo $video["id_video"]; ?>"
>

✏ Editar

</a>



<form method="POST" action="eliminar.php" class="inline-delete-form" onsubmit="return confirmarEliminar()">
<?php echo csrfInput(); ?>
<input type="hidden" name="id" value="<?php echo (int)$video["id_video"]; ?>">
<button type="submit" class="btn-eliminar">🗑 Eliminar</button>
</form>


</div>


</td>



</tr>


<?php endforeach; ?>


<?php else: ?>


<tr>


<td
colspan="9"
class="tabla-vacia"
>

No se encontraron videos con esos filtros.

</td>


</tr>


<?php endif; ?>



</tbody>


</table>



</section>



</div>







</body>

</html>