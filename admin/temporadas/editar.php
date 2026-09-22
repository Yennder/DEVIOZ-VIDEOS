<?php


require_once "../../config/sesion.php";

verificarAdmin();



require_once "../../controllers/TemporadaController.php";



$controller = new TemporadaController();



$id=$_GET["id"];



$temp=$controller->buscar($id);



if($_SERVER["REQUEST_METHOD"]=="POST")
{

    verificarCsrfPost();


    $datos=[


        "id"=>$id,


        "numero"=>$_POST["numero"],


        "titulo"=>$_POST["titulo"],


        "descripcion"=>$_POST["descripcion"]


    ];



    if($controller->actualizar($datos))
    {

        header("Location:listar.php");

        exit;

    }


}



?>


<!DOCTYPE html>

<html lang="es">


<head>

<meta charset="UTF-8">


<title>
Editar Temporada
</title>


<link rel="stylesheet" href="../../assets/css/admin.css">


</head>


<body>



<?php include "../includes/sidebar.php"; ?>


<div class="admin-main">


<?php include "../includes/navbar.php"; ?>



<section class="admin-content">



<h1>
Editar Temporada
</h1>




<form method="POST" class="admin-form">

<?php echo csrfInput(); ?>



<label>
Número:
</label>



<input

type="number"

name="numero"

value="<?php echo $temp["numero_temporada"]; ?>"

>



<label>
Título:
</label>



<input

type="text"

name="titulo"

value="<?php echo htmlspecialchars($temp["titulo"]); ?>"

>



<label>
Descripción:
</label>



<textarea name="descripcion">


<?php echo htmlspecialchars($temp["descripcion"]); ?>


</textarea>




<button class="btn">

Actualizar

</button>




</form>


</section>


</div>



</body>


</html>