<?php


require_once "../../config/sesion.php";

verificarAdmin();



require_once "../../controllers/ConfiguracionController.php";


$controller = new ConfiguracionController();


$error = "";

$mensaje = "";


// =========================================
// OBTENER LOGO ACTUAL
// =========================================

$logoActual = $controller->obtener("logo_sitio");


if(!$logoActual)
{

    $logoActual = "logo_default.png";

}



// =========================================
// PROCESAR FORMULARIO
// =========================================

if($_SERVER["REQUEST_METHOD"] == "POST")
{

    verificarCsrfPost();


    if(
        !isset($_FILES["logo"])
        ||
        $_FILES["logo"]["error"] === UPLOAD_ERR_NO_FILE
    )
    {

        $error =
            "Seleccione un archivo PNG.";

    }
    else
    {


        $archivo =
            $_FILES["logo"];


        if($archivo["error"] !== UPLOAD_ERR_OK)
        {

            $error =
                "Ocurrió un error al subir el archivo.";

        }
        else
        {


            // =========================================
            // VALIDAR EXTENSION
            // =========================================

            $extension =
                strtolower(
                    pathinfo(
                        $archivo["name"],
                        PATHINFO_EXTENSION
                    )
                );


            if($extension !== "png")
            {

                $error =
                    "Solo se permiten imágenes en formato PNG.";

            }
            else
            {


                // =========================================
                // VALIDAR TAMAÑO
                // =========================================

                $maximo =
                    2 * 1024 * 1024;


                if($archivo["size"] > $maximo)
                {

                    $error =
                        "El logo no debe superar los 2 MB.";

                }
                else
                {


                    // =========================================
                    // VALIDAR MIME REAL
                    // =========================================

                    $finfo =
                        finfo_open(
                            FILEINFO_MIME_TYPE
                        );


                    $mime =
                        finfo_file(
                            $finfo,
                            $archivo["tmp_name"]
                        );


                    finfo_close($finfo);


                    if($mime !== "image/png")
                    {

                        $error =
                            "El archivo seleccionado no es un PNG válido.";

                    }
                    else
                    {


                        // =========================================
                        // CREAR CARPETA
                        // =========================================

                        $ruta =
                            __DIR__
                            .
                            "/../../uploads/config/";


                        if(!is_dir($ruta))
                        {

                            mkdir(
                                $ruta,
                                0775,
                                true
                            );

                        }



                        // =========================================
                        // GENERAR NOMBRE
                        // =========================================

                        $nuevoNombre =
                            "logo_"
                            .
                            time()
                            .
                            ".png";


                        $destino =
                            $ruta
                            .
                            $nuevoNombre;



                        // =========================================
                        // MOVER ARCHIVO
                        // =========================================

                        if(
                            move_uploaded_file(
                                $archivo["tmp_name"],
                                $destino
                            )
                        )
                        {


                            // =========================================
                            // ELIMINAR LOGO ANTERIOR
                            // =========================================

                            if(
                                $logoActual
                                &&
                                $logoActual !== "logo_default.png"
                            )
                            {


                                $rutaAnterior =
                                    $ruta
                                    .
                                    $logoActual;


                                if(file_exists($rutaAnterior))
                                {

                                    unlink($rutaAnterior);

                                }

                            }



                            // =========================================
                            // GUARDAR CONFIGURACION
                            // =========================================

                            if(
                                $controller->guardar(
                                    "logo_sitio",
                                    $nuevoNombre
                                )
                            )
                            {

                                $logoActual =
                                    $nuevoNombre;


                                $mensaje =
                                    "Logo actualizado correctamente.";

                            }
                            else
                            {

                                $error =
                                    "No se pudo guardar la configuración del logo.";

                            }


                        }
                        else
                        {

                            $error =
                                "No se pudo mover el archivo al directorio de logos.";

                        }


                    }


                }


            }


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
Configuración - DEVIOZ VIDEOS
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
Configuración
</h1>


<p>
Administra la identidad visual de la plataforma.
</p>


</div>


</div>



<?php if($error): ?>


<div class="mensaje-error">

<?php echo htmlspecialchars($error); ?>

</div>


<?php endif; ?>



<?php if($mensaje): ?>


<div class="mensaje-exito">

<?php echo htmlspecialchars($mensaje); ?>

</div>


<?php endif; ?>



<form
method="POST"
enctype="multipart/form-data"
class="admin-form"
>

<?php echo csrfInput(); ?>



<div class="form-section">


<h2>
Logo del sitio
</h2>



<label>
Logo actual
</label>



<div class="logo-config-preview">


<img
src="../../uploads/config/<?php echo htmlspecialchars($logoActual); ?>"
alt="Logo actual"
>


</div>



<label for="logo">
Nuevo logo
</label>


<input
type="file"
name="logo"
id="logo"
accept=".png,image/png"
required
>



<div class="form-file-info">


<strong>
🖼 Formato permitido
</strong>


<p>
PNG
</p>


<p>
Tamaño máximo: 2 MB
</p>


<p>
Se recomienda utilizar fondo transparente.
</p>


<p>
Dimensión sugerida: 400 × 120 px
</p>


</div>


</div>



<div class="form-actions">


<a
href="../dashboard.php"
class="btn-secundario"
>

← Volver

</a>


<button
type="submit"
class="btn"
>

Guardar Logo

</button>


</div>



</form>



</section>



</div>



</body>


</html>