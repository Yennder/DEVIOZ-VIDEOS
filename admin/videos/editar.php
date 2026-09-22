<?php


require_once "../../config/sesion.php";

verificarAdmin();



require_once "../../controllers/VideoController.php";
require_once "../../config/upload.php";


$videoController = new VideoController();


$error = "";



if(!isset($_GET["id"]))
{

    header("Location: listar.php");

    exit;

}



$id = $_GET["id"];



$video = $videoController->buscar($id);



if(!$video)
{

    header("Location: listar.php");

    exit;

}



$categorias = $videoController->categorias();

$series = $videoController->series();

$temporadas = [];



if(!empty($video["id_serie"]))
{

    require_once "../../models/Temporada.php";


    $tempModel = new Temporada();


    $temporadas = $tempModel->listarPorSerie(
        $video["id_serie"]
    );

}



if($_SERVER["REQUEST_METHOD"] == "POST")
{

    verificarCsrfPost();


    $archivoActual = $video["archivo_video"];

    $imagenActual = $video["miniatura"];



    // =========================================
    // CAMBIAR VIDEO
    // =========================================

    if(
        isset($_FILES["video"])
        &&
        $_FILES["video"]["error"] !== UPLOAD_ERR_NO_FILE
    )
    {


        $nuevoVideo = subirArchivo(
            $_FILES["video"],
            "video"
        );


        if(!$nuevoVideo)
        {

            $error =
                "El nuevo video no cumple con los formatos o tamaños permitidos.";

        }
        else
        {


            $rutaAnterior =
                "../../uploads/videos/"
                .
                $archivoActual;


            if(
                !empty($archivoActual)
                &&
                file_exists($rutaAnterior)
            )
            {

                unlink($rutaAnterior);

            }


            $archivoActual = $nuevoVideo;

        }

    }



    // =========================================
    // CAMBIAR MINIATURA
    // =========================================

    if(
        !$error
        &&
        isset($_FILES["miniatura"])
        &&
        $_FILES["miniatura"]["error"] !== UPLOAD_ERR_NO_FILE
    )
    {


        $nuevaImagen = subirArchivo(
            $_FILES["miniatura"],
            "imagen"
        );


        if(!$nuevaImagen)
        {

            $error =
                "La nueva miniatura no cumple con los formatos o tamaños permitidos.";

        }
        else
        {


            $rutaAnterior =
                "../../uploads/thumbnails/"
                .
                $imagenActual;


            if(
                !empty($imagenActual)
                &&
                file_exists($rutaAnterior)
            )
            {

                unlink($rutaAnterior);

            }


            $imagenActual = $nuevaImagen;

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


            "categoria" =>
                $_POST["categoria"],


            "titulo" =>
                trim($_POST["titulo"]),


            "descripcion" =>
                trim($_POST["descripcion"]),


            "estado" =>
                $_POST["estado"],


            "archivo" =>
                $archivoActual,


            "miniatura" =>
                $imagenActual,


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



        if($videoController->actualizar($datos))
        {

            header(
                "Location:listar.php"
            );


            exit;

        }
        else
        {

            $error =
                "No se pudo actualizar el video.";

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
Editar Video - DEVIOZ VIDEOS
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
Editar Video
</h1>


<p>
Actualiza la información y archivos multimedia del contenido.
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



<label for="titulo">
Título
</label>


<input
type="text"
name="titulo"
id="titulo"
value="<?php echo htmlspecialchars($video["titulo"]); ?>"
required
>



<label for="tipoContenido">
Tipo de contenido
</label>


<select
name="tipo_contenido"
id="tipoContenido"
>


<option
value="video"
<?php echo $video["tipo_contenido"] == "video" ? "selected" : ""; ?>
>

Video independiente

</option>


<option
value="serie"
<?php echo $video["tipo_contenido"] == "serie" ? "selected" : ""; ?>
>

Serie / Capítulo

</option>


</select>



<label for="descripcion">
Descripción
</label>


<textarea
name="descripcion"
id="descripcion"
rows="5"
><?php echo htmlspecialchars($video["descripcion"]); ?></textarea>


</div>



<!-- =========================================
     ARCHIVOS MULTIMEDIA
========================================= -->

<div class="form-section">


<h2>
Archivos multimedia
</h2>



<label for="video">
Cambiar video
</label>


<input
type="file"
name="video"
id="video"
accept=".mp4,.webm,video/mp4,video/webm"
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


<p>
Si no seleccionas un archivo, se conservará el video actual.
</p>


</div>



<label for="miniatura">
Cambiar miniatura
</label>


<input
type="file"
name="miniatura"
id="miniatura"
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
Resolución recomendada: 1280 × 720 px
</p>


<p>
Si no seleccionas una imagen, se conservará la miniatura actual.
</p>


</div>


<?php if(!empty($video["miniatura"])): ?>


<img
src="../../uploads/thumbnails/<?php echo htmlspecialchars($video["miniatura"]); ?>"
class="preview-upload"
alt="Miniatura actual"
>


<?php endif; ?>


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


<?php foreach($categorias as $categoria): ?>


<option
value="<?php echo $categoria["id_categoria"]; ?>"
<?php
echo
$categoria["id_categoria"] == $video["id_categoria"]
?
"selected"
:
"";
?>
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
<?php
echo
$video["tipo_contenido"] == "serie"
?
""
:
'style="display:none;"';
?>
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
<?php
echo
$serie["id_serie"] == $video["id_serie"]
?
"selected"
:
"";
?>
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


<?php foreach($temporadas as $temp): ?>


<option
value="<?php echo $temp["id_temporada"]; ?>"
<?php
echo
$temp["id_temporada"] == $video["id_temporada"]
?
"selected"
:
"";
?>
>

Temporada <?php echo $temp["numero_temporada"]; ?>

</option>


<?php endforeach; ?>


</select>



<label for="numero_capitulo">
Número capítulo
</label>


<input
type="number"
name="numero_capitulo"
id="numero_capitulo"
value="<?php echo htmlspecialchars($video["numero_capitulo"] ?? ""); ?>"
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


<option
value="publicado"
<?php echo $video["estado"] == "publicado" ? "selected" : ""; ?>
>

Publicado

</option>


<option
value="borrador"
<?php echo $video["estado"] == "borrador" ? "selected" : ""; ?>
>

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

Actualizar Video

</button>


</div>



</form>



</section>



</div>






</body>


</html>