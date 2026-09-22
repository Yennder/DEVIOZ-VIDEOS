<?php


require_once "../../config/sesion.php";

verificarAdmin();



require_once "../../controllers/SerieController.php";
require_once "../../config/upload.php";


$controller = new SerieController();


$error = "";



if(!isset($_GET["id"]))
{

    header("Location:listar.php");

    exit;

}



$id = $_GET["id"];


$serie = $controller->buscar($id);



if(!$serie)
{

    header("Location:listar.php");

    exit;

}



if($_SERVER["REQUEST_METHOD"] == "POST")
{

    verificarCsrfPost();


    $titulo =
        trim($_POST["titulo"] ?? "");


    $descripcion =
        trim($_POST["descripcion"] ?? "");


    $estado =
        $_POST["estado"] ?? "1";



    if($titulo == "")
    {

        $error =
            "El título de la serie es obligatorio.";

    }



    // =========================================
    // CAMBIAR PORTADA
    // =========================================

    if(
        !$error
        &&
        isset($_FILES["imagen"])
        &&
        $_FILES["imagen"]["error"] !== UPLOAD_ERR_NO_FILE
    )
    {


        $nuevaImagen =
            subirArchivo(
                $_FILES["imagen"],
                "serie"
            );


        if(!$nuevaImagen)
        {

            $error =
                "La nueva portada no cumple con los formatos o tamaños permitidos.";

        }
        else
        {


            $imagenAnterior =
                $serie["imagen_portada"] ?? "";


            if(
                !empty($imagenAnterior)
                &&
                $imagenAnterior !== "default.jpg"
            )
            {


                $rutaAnterior =
                    "../../uploads/series/"
                    .
                    $imagenAnterior;


                if(file_exists($rutaAnterior))
                {

                    unlink($rutaAnterior);

                }

            }



            $controller->actualizarImagen(
                $id,
                $nuevaImagen
            );


            $serie["imagen_portada"] =
                $nuevaImagen;

        }

    }



    // =========================================
    // ACTUALIZAR DATOS
    // =========================================

    if(!$error)
    {


        $datos = [


            "id" =>
                $id,


            "titulo" =>
                $titulo,


            "descripcion" =>
                $descripcion,


            "estado" =>
                $estado

        ];



        if($controller->actualizar($datos))
        {

            header(
                "Location:listar.php"
            );


            exit;

        }
        else
        {

            $error =
                "No se pudo actualizar la serie.";

        }

    }

}



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
Editar Serie - DEVIOZ VIDEOS
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
Editar Serie
</h1>


<p>
Actualiza la información y portada de la serie.
</p>


</div>


</div>



<?php if($error): ?>


<div class="mensaje-error">

<?php echo htmlspecialchars($error); ?>

</div>


<?php endif; ?>



<form
method="POST"
enctype="multipart/form-data"
class="admin-form serie-edit-form"
>

<?php echo csrfInput(); ?>



<!-- =========================================
     INFORMACION DE LA SERIE
========================================= -->

<div class="form-section">


<h2>
Información de la serie
</h2>



<label for="titulo">
Título
</label>


<input
type="text"
name="titulo"
id="titulo"
value="<?php echo htmlspecialchars($serie["titulo"]); ?>"
required
>



<label for="descripcion">
Descripción
</label>


<textarea
name="descripcion"
id="descripcion"
rows="5"
><?php echo htmlspecialchars($serie["descripcion"]); ?></textarea>


</div>



<!-- =========================================
     PORTADA
========================================= -->

<div class="form-section">


<h2>
Portada
</h2>



<?php if(!empty($serie["imagen_portada"])): ?>


<img
src="../../uploads/series/<?php echo htmlspecialchars($serie["imagen_portada"]); ?>"
class="preview-upload preview-serie"
alt="Portada actual"
>


<?php endif; ?>



<label for="imagen">
Cambiar portada
</label>


<input
type="file"
name="imagen"
id="imagen"
accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
>



<div class="form-file-info">


<strong>
🖼 Formatos de imagen permitidos
</strong>


<p>
JPG, JPEG, PNG y WEBP
</p>


<p>
Tamaño máximo: 5 MB
</p>


<p>
Resolución recomendada: 1000 × 1500 px
</p>


<p>
Orientación recomendada: vertical o tipo póster.
</p>


<p>
Si no seleccionas una imagen, se conservará la portada actual.
</p>


</div>


</div>



<!-- =========================================
     PUBLICACION
========================================= -->

<div class="form-section">


<h2>
Publicación
</h2>



<label for="estado">
Estado
</label>


<select
name="estado"
id="estado"
>


<option
value="1"
<?php echo $serie["estado"] == 1 ? "selected" : ""; ?>
>

Activo

</option>


<option
value="0"
<?php echo $serie["estado"] == 0 ? "selected" : ""; ?>
>

Inactivo

</option>


</select>


</div>



<div class="form-actions">


<a
href="listar.php"
class="btn-secundario"
>

← Cancelar

</a>


<button
type="submit"
class="btn"
>

Actualizar Serie

</button>


</div>



</form>



</section>



</div>






</body>


</html>