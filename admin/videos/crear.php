<?php


require_once "../../config/sesion.php";

verificarAdmin();



require_once "../../controllers/VideoController.php";
require_once "../../config/upload.php";


$videoController = new VideoController();



$categorias = $videoController->categorias();

$series = $videoController->series();

$temporadas = [];

$error = "";



if($_SERVER["REQUEST_METHOD"] == "POST")
{

    verificarCsrfPost();


    $videoArchivo = false;

    $imagenArchivo = false;



    // =========================================
    // VALIDAR Y SUBIR VIDEO
    // =========================================

    if(
        isset($_FILES["video"])
        &&
        $_FILES["video"]["error"] !== UPLOAD_ERR_NO_FILE
    )
    {

        $videoArchivo = subirArchivo(
            $_FILES["video"],
            "video"
        );

    }



    // =========================================
    // VALIDAR Y SUBIR MINIATURA
    // =========================================

    if(
        isset($_FILES["miniatura"])
        &&
        $_FILES["miniatura"]["error"] !== UPLOAD_ERR_NO_FILE
    )
    {

        $imagenArchivo = subirArchivo(
            $_FILES["miniatura"],
            "imagen"
        );

    }



    // =========================================
    // VALIDAR RESULTADO DE ARCHIVOS
    // =========================================

    if(!$videoArchivo || !$imagenArchivo)
    {

        $error =
            "No se pudieron subir los archivos. "
            .
            "Verifique que el video y la miniatura cumplan con los formatos y tamaños permitidos.";

    }
    else
    {


        $datos = [


            "categoria" =>
                $_POST["categoria"],


            "usuario" =>
                $_SESSION["id_usuario"],


            "titulo" =>
                trim($_POST["titulo"]),


            "descripcion" =>
                trim($_POST["descripcion"]),


            "estado" =>
                $_POST["estado"],


            "archivo" =>
                $videoArchivo,


            "miniatura" =>
                $imagenArchivo,


            "tipo_contenido" =>
                $_POST["tipo_contenido"],



            "id_serie" =>
            (
                $_POST["tipo_contenido"] == "serie"
                &&
                !empty($_POST["serie"])
            )

            ? $_POST["serie"]

            : null,



            "id_temporada" =>
            (
                $_POST["tipo_contenido"] == "serie"
                &&
                !empty($_POST["temporada"])
            )

            ? $_POST["temporada"]

            : null,



            "numero_capitulo" =>
            (
                $_POST["tipo_contenido"] == "serie"
                &&
                !empty($_POST["numero_capitulo"])
            )

            ? $_POST["numero_capitulo"]

            : null

        ];



        if($videoController->crear($datos))
        {

            header(
                "Location: listar.php"
            );


            exit;

        }
        else
        {

            $error =
                "No se pudo registrar el video.";

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
Crear Video - DEVIOZ VIDEOS
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
Nuevo Video
</h1>


<p>
Registra un video independiente o un capítulo de una serie.
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
     INFORMACION DEL CONTENIDO
========================================= -->

<div class="form-section">


<h2>
Información del contenido
</h2>



<label for="tipoContenido">
Tipo de contenido
</label>


<select
name="tipo_contenido"
id="tipoContenido"
>


<option value="video">

Video independiente

</option>


<option value="serie">

Serie / Capítulo

</option>


</select>



<label for="titulo">
Título
</label>


<input
type="text"
name="titulo"
id="titulo"
required
>



<label for="descripcion">
Descripción
</label>


<textarea
name="descripcion"
id="descripcion"
rows="5"
></textarea>


</div>



<!-- =========================================
     ARCHIVOS MULTIMEDIA
========================================= -->

<div class="form-section">


<h2>
Archivos multimedia
</h2>



<label for="video">
Archivo de video
</label>


<input
type="file"
name="video"
id="video"
accept=".mp4,.webm,video/mp4,video/webm"
required
>



<div class="form-file-info">


<strong>
🎬 Formatos de video permitidos
</strong>


<p>
MP4 y WEBM
</p>


<p>
Tamaño máximo: 1 GB
</p>


<p>
Resolución recomendada: hasta 1920 × 1080 px
</p>


</div>



<label for="miniatura">
Miniatura
</label>


<input
type="file"
name="miniatura"
id="miniatura"
accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
required
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
Resolución recomendada: 1280 × 720 px
</p>


</div>



<img
id="preview"
class="preview-upload"
alt="Vista previa de la miniatura"
>


</div>



<!-- =========================================
     CLASIFICACION
========================================= -->

<div class="form-section">


<h2>
Clasificación
</h2>



<label for="categoria">
Categoría
</label>


<select
name="categoria"
id="categoria"
required
>


<option value="">

Seleccione categoría

</option>



<?php foreach($categorias as $categoria): ?>


<option
value="<?php echo $categoria["id_categoria"]; ?>"
>

<?php echo htmlspecialchars($categoria["nombre"]); ?>

</option>


<?php endforeach; ?>


</select>


</div>



<!-- =========================================
     DATOS DE SERIE
========================================= -->

<div
id="datosSerie"
class="form-section"
style="display:none;"
>


<h2>
Datos del capítulo
</h2>



<label for="serie">
Serie
</label>


<select
name="serie"
id="serie"
>


<option value="">

Seleccione serie

</option>



<?php foreach($series as $serie): ?>


<option
value="<?php echo $serie["id_serie"]; ?>"
>

<?php echo htmlspecialchars($serie["titulo"]); ?>

</option>


<?php endforeach; ?>


</select>



<label for="temporada">
Temporada
</label>


<select
name="temporada"
id="temporada"
>


<option value="">

Seleccione temporada

</option>


</select>



<label for="numero_capitulo">
Número de capítulo
</label>


<input
type="number"
name="numero_capitulo"
id="numero_capitulo"
min="1"
>


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


<option value="publicado">

Publicado

</option>


<option value="borrador">

Borrador

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

Guardar Video

</button>


</div>



</form>



</section>



</div>







</body>


</html>