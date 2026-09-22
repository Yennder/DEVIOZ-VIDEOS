<?php


require_once "../../config/sesion.php";

verificarAdmin();



require_once "../../controllers/SerieController.php";


$controller = new SerieController();



// =========================================
// FILTROS
// =========================================

$buscar = trim($_GET["buscar"] ?? "");

$estado = $_GET["estado"] ?? "";

$orden = $_GET["orden"] ?? "recientes";



$series = $controller->listarFiltrado(
    $buscar,
    $estado,
    $orden
);



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
Series - DEVIOZ VIDEOS
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
Gestión de Series
</h1>


<p>
Administra las series, temporadas y capítulos registrados.
</p>


</div>



<a
href="crear.php"
class="btn"
>

+ Nueva Serie

</a>


</div>





<!-- =========================================
     FILTROS
========================================= -->


<form
method="GET"
class="filtros-admin filtros-series"
>


<div class="filtro-busqueda">


<label>
Buscar
</label>


<input
type="text"
name="buscar"
placeholder="Buscar serie por título..."
value="<?php echo htmlspecialchars($buscar); ?>"
>


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
value="1"
<?php echo $estado === "1" ? "selected" : ""; ?>
>

Activo

</option>


<option
value="0"
<?php echo $estado === "0" ? "selected" : ""; ?>
>

Inactivo

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
value="antiguas"
<?php echo $orden === "antiguas" ? "selected" : ""; ?>
>

Más antiguas

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


<option
value="mas_temporadas"
<?php echo $orden === "mas_temporadas" ? "selected" : ""; ?>
>

Más temporadas

</option>


<option
value="mas_capitulos"
<?php echo $orden === "mas_capitulos" ? "selected" : ""; ?>
>

Más capítulos

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




<div class="resultado-filtros">


<span>

<?php echo count($series); ?>

resultado<?php echo count($series) != 1 ? "s" : ""; ?>

</span>


</div>





<table class="admin-table series-table">


<thead>


<tr>


<th>ID</th>

<th>Portada</th>

<th>Título</th>

<th>Temporadas</th>

<th>Capítulos</th>

<th>Estado</th>

<th>Fecha</th>

<th>Acciones</th>


</tr>


</thead>




<tbody>



<?php if(!empty($series)): ?>


<?php foreach($series as $serie): ?>


<tr>



<td>

<?php echo $serie["id_serie"]; ?>

</td>




<td>


<?php if(!empty($serie["imagen_portada"])): ?>


<img
class="serie-thumb-admin"
src="../../uploads/series/<?php echo htmlspecialchars($serie["imagen_portada"]); ?>"
alt="<?php echo htmlspecialchars($serie["titulo"]); ?>"
>


<?php else: ?>


<span class="sin-portada-tabla">

Sin imagen

</span>


<?php endif; ?>


</td>




<td>


<strong>

<?php echo htmlspecialchars($serie["titulo"]); ?>

</strong>


</td>




<td>


<span class="serie-count-badge">

📚 <?php echo $serie["total_temporadas"]; ?>

</span>


</td>




<td>


<span class="serie-count-badge">

🎬 <?php echo $serie["total_capitulos"]; ?>

</span>


</td>




<td>


<span
class="estado <?php echo $serie["estado"] ? "activo" : "inactivo"; ?>"
>

<?php echo $serie["estado"] ? "Activo" : "Inactivo"; ?>

</span>


</td>




<td>

<?php echo $serie["fecha_creacion"]; ?>

</td>




<td class="acciones">


<div class="acciones-contenedor">


<a
href="editar.php?id=<?php echo $serie["id_serie"]; ?>"
class="btn-editar"
>

✏ Editar

</a>



<form method="POST" action="eliminar.php" class="inline-delete-form" onsubmit="return confirmarEliminar()">
<?php echo csrfInput(); ?>
<input type="hidden" name="id" value="<?php echo (int)$serie["id_serie"]; ?>">
<button type="submit" class="btn-eliminar">🗑 Eliminar</button>
</form>


</div>


</td>



</tr>


<?php endforeach; ?>


<?php else: ?>


<tr>


<td
colspan="8"
class="tabla-vacia"
>

No se encontraron series con esos filtros.

</td>


</tr>


<?php endif; ?>



</tbody>


</table>



</section>



</div>







</body>

</html>