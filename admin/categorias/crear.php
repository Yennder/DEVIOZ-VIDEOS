<?php


require_once "../../config/sesion.php";

verificarAdmin();



require_once "../../controllers/CategoriaController.php";


$categoriaController = new CategoriaController();



$mensaje = "";



if($_SERVER["REQUEST_METHOD"] == "POST")
{

    verificarCsrfPost();


    $nombre = $_POST["nombre"];

    $descripcion = $_POST["descripcion"];



    if(
        $categoriaController->crear(
            $nombre,
            $descripcion
        )
    )
    {

        header(
            "Location: listar.php"
        );

        exit;

    }
    else
    {

        $mensaje = "Error al crear categoría";

    }


}


?>



<!DOCTYPE html>

<html lang="es">


<head>


<meta charset="UTF-8">


<title>
Crear Categoría - DEVIOZ VIDEOS
</title>


<link rel="stylesheet" href="../../assets/css/admin.css">


</head>



<body>



<?php include "../includes/sidebar.php"; ?>



<div class="admin-main">



<?php include "../includes/navbar.php"; ?>



<section class="admin-content">



<h1>
Nueva Categoría
</h1>




<?php if($mensaje): ?>


<p class="mensaje-error">

<?php echo htmlspecialchars($mensaje); ?>

</p>


<?php endif; ?>





<form method="POST" class="admin-form">

<?php echo csrfInput(); ?>



<label>
Nombre:
</label>



<input 
type="text"
name="nombre"
required
>




<label>
Descripción:
</label>



<textarea

name="descripcion"

rows="5"

></textarea>




<button type="submit" class="btn">

Guardar categoría

</button>



</form>



</section>



</div>







</body>


</html>