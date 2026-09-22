<?php


require_once "../../config/sesion.php";

verificarAdmin();



require_once "../../controllers/CategoriaController.php";


$categoriaController = new CategoriaController();



if(!isset($_GET["id"]))
{

    header("Location: listar.php");

    exit;

}



$id = $_GET["id"];



$categoria = $categoriaController->buscar($id);



if(!$categoria)
{

    header("Location: listar.php");

    exit;

}



if($_SERVER["REQUEST_METHOD"] == "POST")
{

    verificarCsrfPost();


    $nombre = $_POST["nombre"];

    $descripcion = $_POST["descripcion"];



    if(
        $categoriaController->actualizar(
            $id,
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


}



?>


<!DOCTYPE html>

<html lang="es">


<head>

<meta charset="UTF-8">


<title>
Editar Categoría
</title>


<link rel="stylesheet" href="../../assets/css/admin.css">


</head>



<body>



<?php include "../includes/sidebar.php"; ?>



<div class="admin-main">



<?php include "../includes/navbar.php"; ?>



<section class="admin-content">



<h1>
Editar Categoría
</h1>




<form method="POST" class="admin-form">

<?php echo csrfInput(); ?>



<label>
Nombre:
</label>



<input 
type="text"
name="nombre"
value="<?php echo htmlspecialchars($categoria['nombre']); ?>"
required
>




<label>
Descripción:
</label>



<textarea 
name="descripcion"
rows="5"
>

<?php echo htmlspecialchars($categoria['descripcion']); ?>

</textarea>




<button type="submit" class="btn">

Actualizar categoría

</button>




</form>



</section>



</div>







</body>


</html>