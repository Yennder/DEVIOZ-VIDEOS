<?php


require_once "../../config/sesion.php";

verificarAdmin();


require_once "../../controllers/TemporadaController.php";


$controller = new TemporadaController();



// =========================================
// FILTROS
// =========================================

$buscar = trim($_GET["buscar"] ?? "");

$serie = $_GET["serie"] ?? "";

$orden = $_GET["orden"] ?? "serie";



$temporadas = $controller->listarFiltrado(
    $buscar,
    $serie,
    $orden
);


$seriesFiltro = $controller->seriesFiltro();



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
Temporadas - DEVIOZ VIDEOS
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
Gestión de Temporadas
</h1>

<p>
Administra las temporadas asociadas a cada serie.
</p>

</div>


<a
href="crear.php"
class="btn"
>

+ Nueva Temporada

</a>


</div>



<!-- FILTROS -->


<form
method="GET"
class="filtros-admin filtros-temporadas"
>


<div class="filtro-busqueda">

<label>
Buscar
</label>

<input
type="text"
name="buscar"
placeholder="Buscar temporada o serie..."
value="<?php echo htmlspecialchars($buscar); ?>"
>

</div>



<div>

<label>
Serie
</label>

<select name="serie">

<option value="">
Todas
</option>


<?php foreach($seriesFiltro as $serieItem): ?>

<option
value="<?php echo $serieItem["id_serie"]; ?>"
<?php echo $serie == $serieItem["id_serie"] ? "selected" : ""; ?>
>

<?php echo htmlspecialchars($serieItem["titulo"]); ?>

</option>

<?php endforeach; ?>

</select>

</div>



<div>

<label>
Orden
</label>

<select name="orden">


<option
value="serie"
<?php echo $orden === "serie" ? "selected" : ""; ?>
>
Serie / Temporada
</option>


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
value="numero_asc"
<?php echo $orden === "numero_asc" ? "selected" : ""; ?>
>
Número menor a mayor
</option>


<option
value="numero_desc"
<?php echo $orden === "numero_desc" ? "selected" : ""; ?>
>
Número mayor a menor
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

<?php echo count($temporadas); ?>
resultado<?php echo count($temporadas) != 1 ? "s" : ""; ?>

</div>



<table class="admin-table temporadas-table">


<thead>

<tr>

<th>ID</th>

<th>Serie</th>

<th>Temporada</th>

<th>Título</th>

<th>Capítulos</th>

<th>Fecha</th>

<th>Acciones</th>

</tr>

</thead>


<tbody>


<?php if(!empty($temporadas)): ?>


<?php foreach($temporadas as $temp): ?>


<tr>


<td>

<?php echo $temp["id_temporada"]; ?>

</td>


<td>

<strong>
<?php echo htmlspecialchars($temp["serie"]); ?>
</strong>

</td>


<td>

<span class="temporada-badge">

T<?php echo $temp["numero_temporada"]; ?>

</span>

</td>


<td>

<?php echo htmlspecialchars($temp["titulo"]); ?>

</td>


<td>

<span class="serie-count-badge">

🎬 <?php echo $temp["total_capitulos"]; ?>

</span>

</td>


<td>

<?php echo $temp["fecha_creacion"]; ?>

</td>


<td class="acciones">

<div class="acciones-contenedor">


<a
class="btn-editar"
href="editar.php?id=<?php echo $temp["id_temporada"]; ?>"
>

✏ Editar

</a>


<form method="POST" action="eliminar.php" class="inline-delete-form" onsubmit="return confirmarEliminar()">
<?php echo csrfInput(); ?>
<input type="hidden" name="id" value="<?php echo (int)$temp["id_temporada"]; ?>">
<button type="submit" class="btn-eliminar">🗑 Eliminar</button>
</form>


</div>

</td>


</tr>


<?php endforeach; ?>


<?php else: ?>


<tr>

<td
colspan="7"
class="tabla-vacia"
>

No se encontraron temporadas.

</td>

</tr>


<?php endif; ?>


</tbody>


</table>


</section>


</div>





</body>

</html>