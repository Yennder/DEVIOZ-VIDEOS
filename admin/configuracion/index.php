<?php


require_once "../../config/sesion.php";

verificarAdmin();



require_once "../../controllers/ConfiguracionController.php";
require_once "../../includes/portada_video.php";


$controller = new ConfiguracionController();


$error = "";

$mensaje = "";

// HOTFIX V4.5.1.1: formularios independientes para logo y video promocional.
$esPost = ($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST";
$postSinDatos = $esPost && empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;
$accionConfiguracion = is_string($_POST['accion'] ?? null) ? $_POST['accion'] : 'logo';
$videoPortadaAnterior = $controller->obtener('video_portada');


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

if($esPost && !$postSinDatos && $accionConfiguracion === "logo")
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




// Video de portada: se guarda en una carpeta publica separada de uploads/videos.
if ($postSinDatos) {
    $error = 'La carga excede el limite post_max_size de PHP. Revisa php.ini y vuelve a intentarlo.';
} elseif ($esPost && in_array($accionConfiguracion, ['subir_portada', 'quitar_portada'], true)) {
    verificarCsrfPost();
    $nuevoVideo = null;
    try {
        if ($accionConfiguracion === 'subir_portada') {
            $nuevoVideo = deviozSubirVideoPortada($_FILES['video_portada'] ?? null);
            if (!$controller->guardar('video_portada', $nuevoVideo)) {
                throw new RuntimeException('No se pudo registrar el video en la configuracion.');
            }
            deviozEliminarVideoPortada($videoPortadaAnterior);
            $mensaje = 'Video de portada actualizado. Ya se puede ver en el Inicio.';
            $nuevoVideo = null;
        } else {
            // Desactivar explicitamente, incluso si existe el MP4 de la version anterior.
            if (!$controller->guardar('video_portada', 'desactivado')) {
                throw new RuntimeException('No se pudo desactivar el video de portada.');
            }
            deviozEliminarVideoPortada($videoPortadaAnterior);
            $mensaje = 'Video de portada quitado. El Inicio mostrara el fondo alternativo.';
        }
    } catch (Throwable $e) {
        if ($nuevoVideo !== null) {
            deviozEliminarVideoPortada($nuevoVideo);
        }
        $error = $e instanceof RuntimeException
            ? $e->getMessage()
            : 'No se pudo actualizar el video. Comprueba el formato y los permisos.';
    }
}

$videoPortadaGuardado = $controller->obtener('video_portada');
$videoPortadaSrc = deviozFuenteVideoPortada($videoPortadaGuardado, '../../');
$videoPortadaNoEncontrado = deviozNombreVideoPortadaValido($videoPortadaGuardado)
    && $videoPortadaSrc === null;

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


<style>
.portada-admin-ayuda{color:var(--adm-muted,#667085);font-size:13px;line-height:1.6;margin:12px 0;}
.portada-admin-preview{width:min(100%,850px);border-radius:14px;border:1px solid var(--adm-border,#d6e0e7);overflow:hidden;background:linear-gradient(110deg,#071625,#163644);aspect-ratio:16/9;margin:12px 0 18px;display:flex;align-items:center;justify-content:center;}
.portada-admin-preview video{display:block;width:100%;height:100%;object-fit:cover;}
.portada-admin-preview p{color:#d8e8ee;text-align:center;padding:22px;margin:0;font-weight:650;}
.portada-admin-alerta{margin:10px 0;color:#bb6b24;font-size:13px;}
.portada-admin-actions{display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin-top:18px;}
.portada-admin-actions form{margin:0;}
.portada-admin-actions .btn,.portada-admin-actions .btn-secundario{font:inherit;font-weight:700;cursor:pointer;padding:11px 18px;}
.portada-admin-boton-quitar{background:transparent!important;border:1px solid #b45059!important;color:#b45059!important;}
.portada-admin-boton-quitar:hover{background:rgba(180,80,89,.09)!important;}
.portada-admin-etiqueta{font-size:13px;line-height:1.6;color:var(--adm-text,#243444);}
.portada-admin-enlace{color:#0a9db2;text-decoration:underline;text-underline-offset:3px;}
</style>
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
Administra el logo y el video de fondo de la portada de Inicio.
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
<input type="hidden" name="accion" value="logo">



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

<form method="POST" enctype="multipart/form-data" class="admin-form" aria-label="Configurar video de portada">
    <?php echo csrfInput(); ?>
    <input type="hidden" name="accion" value="subir_portada">
    <div class="form-section">
        <h2>Video de fondo de la portada</h2>
        <p class="portada-admin-ayuda">
            Se mostrara detras del texto "Contenido tecnologico para seguir avanzando" en la pagina de Inicio.
            Es un video <strong>promocional publico</strong>: no uses material privado ni videos protegidos del catalogo.
        </p>

        <label>Video actual</label>
        <div class="portada-admin-preview">
            <?php if ($videoPortadaSrc !== null): ?>
                <video controls muted playsinline preload="metadata">
                    <source src="<?php echo htmlspecialchars($videoPortadaSrc, ENT_QUOTES, 'UTF-8'); ?>" type="video/mp4">
                    Tu navegador no admite este video.
                </video>
            <?php else: ?>
                <p>No hay video de portada activo. Se mostrara el fondo alternativo.</p>
            <?php endif; ?>
        </div>

        <?php if ($videoPortadaNoEncontrado): ?>
            <p class="portada-admin-alerta">El MP4 configurado no se encuentra en el servidor. Sube otro para repararlo.</p>
        <?php endif; ?>

        <label for="video_portada">Subir o reemplazar video</label>
        <input type="file" id="video_portada" name="video_portada" accept=".mp4,video/mp4" required>
        <div class="form-file-info">
            <strong>Formato MP4 (video H.264 recomendado)</strong>
            <p>Tamano maximo permitido por el sistema: 100 MB. Recomendamos un clip de 8-20 segundos, sin audio y optimizado a menos de 20 MB.</p>
            <p>Limites actuales de PHP: upload_max_filesize = <?php echo htmlspecialchars((string)ini_get('upload_max_filesize'), ENT_QUOTES, 'UTF-8'); ?>; post_max_size = <?php echo htmlspecialchars((string)ini_get('post_max_size'), ENT_QUOTES, 'UTF-8'); ?>.</p>
            <p>Si el MP4 supera esos limites, debes aumentarlos en php.ini de XAMPP y reiniciar Apache.</p>
            <p>Puedes descargar un clip autorizado desde <a class="portada-admin-enlace" href="https://mixkit.co/free-stock-video/open-office-space-and-staircase-917/" target="_blank" rel="noopener noreferrer">Mixkit</a> y subir aqui su archivo MP4.</p>
        </div>
    </div>
    <div class="form-actions">
        <a href="../../public/index.php" class="btn-secundario" target="_blank" rel="noopener">Ver pagina de Inicio</a>
        <button class="btn" type="submit">Guardar video de portada</button>
    </div>
</form>

<?php if ($videoPortadaSrc !== null || $videoPortadaNoEncontrado): ?>
<form method="POST" class="portada-admin-actions" onsubmit="return confirm('Se quitara el video actual de la portada. ¿Continuar?');">
    <?php echo csrfInput(); ?>
    <input type="hidden" name="accion" value="quitar_portada">
    <button type="submit" class="btn-secundario portada-admin-boton-quitar">Quitar video de portada</button>
</form>
<?php endif; ?>

</section>



</div>



</body>


</html>