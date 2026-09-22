<?php


require_once "../../config/sesion.php";

verificarAdmin();



require_once "../../controllers/UsuarioController.php";


$controller = new UsuarioController();


$usuarios = $controller->listar();



?>


<!DOCTYPE html>

<html lang="es">


<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">


<title>
Usuarios - DEVIOZ VIDEOS
</title>


<link rel="stylesheet" href="../../assets/css/admin.css">


</head>


<body>



<?php include "../includes/sidebar.php"; ?>



<div class="admin-main">



<?php include "../includes/navbar.php"; ?>



<section class="admin-content">



<h1>
Gestión de Usuarios
</h1>



<a href="crear.php" class="btn">

+ Nuevo Usuario

</a>




<table class="admin-table usuarios-table">


<thead>


<tr>

<th>ID</th>

<th>Nombre</th>

<th>Correo</th>

<th>Rol</th>

<th>Estado</th>

<th>Fecha</th>

<th>Acciones</th>

</tr>


</thead>




<tbody>



<?php foreach($usuarios as $usuario): ?>


<tr>


<td>

<?php echo $usuario["id_usuario"]; ?>

</td>




<td>

<strong>

<?php echo htmlspecialchars($usuario["nombre"]); ?>

</strong>

</td>




<td>

<?php echo htmlspecialchars($usuario["email"]); ?>

</td>




<td>


<?php if($usuario["rol"] === "admin"): ?>


<span class="rol-badge rol-admin">

Administrador

</span>


<?php else: ?>

<span class="rol-badge rol-usuario">

Editor

</span>


<?php endif; ?>


</td>




<td>


<?php if($usuario["estado"] == 1): ?>


<span class="estado activo">

Activo

</span>


<?php else: ?>


<span class="estado inactivo">

Inactivo

</span>


<?php endif; ?>


</td>




<td>

<?php echo $usuario["fecha_creacion"]; ?>

</td>




<td class="acciones">


<div class="acciones-contenedor">



<a

href="editar.php?id=<?php echo $usuario["id_usuario"]; ?>"

class="btn-editar"

>

✏ Editar

</a>




<?php if($usuario["id_usuario"] != $_SESSION["id_usuario"]): ?>


<form method="POST" action="eliminar.php" class="inline-delete-form" onsubmit="return confirm('¿Seguro que deseas eliminar este usuario?');">
<?php echo csrfInput(); ?>
<input type="hidden" name="id" value="<?php echo (int)$usuario["id_usuario"]; ?>">
<button type="submit" class="btn-eliminar">🗑 Eliminar</button>
</form>


<?php else: ?>


<span class="usuario-actual">

Tu cuenta

</span>


<?php endif; ?>



</div>


</td>



</tr>


<?php endforeach; ?>



</tbody>


</table>



</section>


</div>



</body>

</html>