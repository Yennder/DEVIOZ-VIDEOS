<?php


require_once "../../config/sesion.php";

verificarAdmin();



require_once "../../controllers/TemporadaController.php";

require_once "../../controllers/SerieController.php";



$controller = new TemporadaController();

$serieController = new SerieController();



$series = $serieController->listar();



if($_SERVER["REQUEST_METHOD"]=="POST")
{

    verificarCsrfPost();


    $datos=[


        "serie"=>$_POST["serie"],


        "numero"=>$_POST["numero"],


        "titulo"=>$_POST["titulo"],


        "descripcion"=>$_POST["descripcion"]


    ];



    if($controller->crear($datos))
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
Crear Temporada
</title>


<link rel="stylesheet" href="../../assets/css/admin.css">


</head>



<body>



<?php include "../includes/sidebar.php"; ?>


<div class="admin-main">


<?php include "../includes/navbar.php"; ?>



<section class="admin-content">


<h1>
Nueva Temporada
</h1>




<form method="POST" class="admin-form">

<?php echo csrfInput(); ?>



<label>
Serie:
</label>


<select name="serie" required>


<option value="">
Seleccione serie
</option>



<?php foreach($series as $serie): ?>


<option value="<?php echo (int)$serie["id_serie"]; ?>">


<?php echo htmlspecialchars($serie["titulo"]); ?>


</option>


<?php endforeach; ?>


</select>





<label>
Número de temporada:
</label>



<input

type="number"

name="numero"

min="1"

required

>




<label>
Título:
</label>



<input

type="text"

name="titulo"

placeholder="Temporada 1"

required

>




<label>
Descripción:
</label>


<textarea

name="descripcion"

rows="5"

></textarea>





<button class="btn">

Guardar Temporada

</button>




</form>


</section>


</div>



</body>


</html>