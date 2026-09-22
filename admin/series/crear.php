<?php


require_once "../../config/sesion.php";

verificarAdmin();



require_once "../../controllers/SerieController.php";
require_once "../../config/upload.php";


$controller = new SerieController();


$error = "";



if($_SERVER["REQUEST_METHOD"] == "POST")
{

    verificarCsrfPost();


    $titulo =
        trim($_POST["titulo"] ?? "");


    $descripcion =
        trim($_POST["descripcion"] ?? "");


    $estado =
        $_POST["estado"] ?? "1";


    $imagen =
        "default.jpg";



    // =========================================
    // VALIDAR CAMPOS
    // =========================================

    if($titulo == "")
    {

        $error =
            "El título de la serie es obligatorio.";

    }



    // =========================================
    // SUBIR PORTADA
    // =========================================

    if(
        !$error
        &&
        isset($_FILES["imagen"])
        &&
        $_FILES["imagen"]["error"] !== UPLOAD_ERR_NO_FILE
    )
    {


        $imagenSubida =
            subirArchivo(
                $_FILES["imagen"],
                "serie"
            );


        if(!$imagenSubida)
        {

            $error =
                "No se pudo subir la portada. "
                .
                "Verifique que sea JPG, JPEG, PNG o WEBP y que no supere los 5 MB.";

        }
        else
        {

            $imagen =
                $imagenSubida;

        }

    }



    // =========================================
    // CREAR SERIE
    // =========================================

    if(!$error)
    {


        $datos = [


            "titulo" =>
                $titulo,


            "descripcion" =>
                $descripcion,


            "imagen" =>
                $imagen,


            "estado" =>
                $estado


        ];



        if($controller->crear($datos))
        {

            header(
                "Location:listar.php"
            );


            exit;

        }
        else
        {

            $error =
                "No se pudo registrar la serie.";

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
Crear Serie - DEVIOZ VIDEOS
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
Nueva Serie
</h1>


<p>
Registra una nueva serie y configura su portada.
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
class="admin-form"
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
value="<?php echo htmlspecialchars($_POST["titulo"] ?? ""); ?>"
required
>



<label for="descripcion">
Descripción
</label>


<textarea
name="descripcion"
id="descripcion"
rows="5"
><?php echo htmlspecialchars($_POST["descripcion"] ?? ""); ?></textarea>


</div>



<!-- =========================================
     PORTADA
========================================= -->

<div class="form-section">


<h2>
Portada
</h2>



<label for="imagen">
Imagen de portada
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
Orientación recomendada: vertical
</p>


</div>



<img
id="preview"
class="preview-upload preview-serie"
alt="Vista previa de la portada"
>


</div>



<!-- =========================================
     ESTADO
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
<?php echo (($_POST["estado"] ?? "1") == "1") ? "selected" : ""; ?>
>

Activo

</option>


<option
value="0"
<?php echo (($_POST["estado"] ?? "") == "0") ? "selected" : ""; ?>
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

Guardar Serie

</button>


</div>



</form>



</section>



</div>






</body>


</html>