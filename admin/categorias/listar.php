<?php


require_once "../../config/sesion.php";

verificarAdmin();


require_once "../../controllers/CategoriaController.php";


$categoriaController = new CategoriaController();



$buscar = trim($_GET["buscar"] ?? "");

$estado = $_GET["estado"] ?? "";

$orden = $_GET["orden"] ?? "recientes";



$categorias = $categoriaController->listarFiltrado(
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
Categorías - DEVIOZ VIDEOS
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
Gestión de Categorías
</h1>

<p>
Organiza y administra las categorías del contenido.
</p>

</div>


<a
href="crear.php"
class="btn"
>

+ Nueva Categoría

</a>


</div>



<form
method="GET"
class="filtros-admin filtros-categorias"
>


<div class="filtro-busqueda">

<label>
Buscar
</label>

<input
type="text"
name="buscar"
placeholder="Buscar categoría..."
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
value="nombre_az"
<?php echo $orden === "nombre_az" ? "selected" : ""; ?>
>
Nombre A-Z
</option>


<option
value="nombre_za"
<?php echo $orden === "nombre_za" ? "selected" : ""; ?>
>
Nombre Z-A
</option>


<option
value="mas_contenido"
<?php echo $orden === "mas_contenido" ? "selected" : ""; ?>
>
Más contenido
</option>


<option
value="menos_contenido"
<?php echo $orden === "menos_contenido" ? "selected" : ""; ?>
>
Menos contenido
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

<?php echo count($categorias); ?>
resultado<?php echo count($categorias) != 1 ? "s" : ""; ?>

</div>



<table class="admin-table categorias-table">


<thead>

<tr>

<th>ID</th>

<th>Nombre</th>

<th>Descripción</th>

<th>Contenido</th>

<th>Estado</th>

<th>Fecha</th>

<th>Acciones</th>

</tr>

</thead>


<tbody>


<?php if(!empty($categorias)): ?>


<?php foreach($categorias as $categoria): ?>


<tr>


<td>

<?php echo $categoria["id_categoria"]; ?>

</td>


<td>

<strong>

<?php echo htmlspecialchars($categoria["nombre"]); ?>

</strong>

</td>


<td>

<?php echo htmlspecialchars($categoria["descripcion"]); ?>

</td>


<td>

<span class="serie-count-badge">

🎬 <?php echo $categoria["total_videos"]; ?>

</span>

</td>


<td>

<span
class="estado <?php echo $categoria["estado"] == 1 ? "activo" : "inactivo"; ?>"
>

<?php echo $categoria["estado"] == 1 ? "Activo" : "Inactivo"; ?>

</span>

</td>


<td>

<?php echo $categoria["fecha_creacion"]; ?>

</td>


<td class="acciones">

<div class="acciones-contenedor">


<a
class="btn-editar"
href="editar.php?id=<?php echo $categoria["id_categoria"]; ?>"
>

✏ Editar

</a>


<form method="POST" action="eliminar.php" class="inline-delete-form" onsubmit="return confirmarEliminar()">
<?php echo csrfInput(); ?>
<input type="hidden" name="id" value="<?php echo (int)$categoria["id_categoria"]; ?>">
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

No se encontraron categorías.

</td>

</tr>


<?php endif; ?>


</tbody>


</table>


</section>


</div>





</body>

</html>