<?php


require_once "../config/sesion.php";

verificarSesion();


require_once "../controllers/UsuarioController.php";
require_once "../controllers/ConfiguracionController.php";
require_once "../controllers/InteraccionController.php";


$usuarioController =
    new UsuarioController();


$configController =
    new ConfiguracionController();

$interaccionController =
    new InteraccionController();


$error = "";

$mensaje = "";


// =========================================
// BUSCAR USUARIO
// =========================================

$usuario =
    $usuarioController->buscar(
        $_SESSION["id_usuario"]
    );


if(!$usuario)
{

    header(
        "Location: ../public/index.php"
    );

    exit;

}

$resumenActividad = $interaccionController->resumenUsuario((int)$_SESSION["id_usuario"]);


// =========================================
// LOGO
// =========================================

$logoSitio =
    $configController->obtener(
        "logo_sitio"
    );


if(!$logoSitio)
{

    $logoSitio =
        "logo_default.png";

}


// =========================================
// ACTUALIZAR PERFIL
// =========================================

if($_SERVER["REQUEST_METHOD"] === "POST")
{

    verificarCsrfPost();


    $nombre =
        trim(
            $_POST["nombre"] ?? ""
        );


    $email =
        trim(
            $_POST["email"] ?? ""
        );


    if(
        $nombre === ""
        ||
        $email === ""
    )
    {

        $error =
            "Complete todos los campos.";

    }
    elseif(
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    )
    {

        $error =
            "Ingrese un correo electrónico válido.";

    }
    else
    {


        $datos = [


            "id" =>
                $_SESSION["id_usuario"],


            "nombre" =>
                $nombre,


            "email" =>
                $email,


            // El usuario NO puede modificar esto

            "rol" =>
                $usuario["rol"],


            "estado" =>
                $usuario["estado"]

        ];


        if(
            $usuarioController->actualizar(
                $datos
            )
        )
        {

            $_SESSION["nombre"] =
                $nombre;


            $mensaje =
                "Perfil actualizado correctamente.";


            // Volvemos a consultar
            // los datos actualizados

            $usuario =
                $usuarioController->buscar(
                    $_SESSION["id_usuario"]
                );

        }
        else
        {

            $error =
                "No se pudo actualizar el perfil. El correo podría estar registrado.";

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
Mi perfil - DEVIOZ VIDEOS
</title>


<link
rel="stylesheet"
href="../assets/css/login.css"
>


</head>


<body>


<div class="login-page">


    <div class="login-glow glow-one"></div>

    <div class="login-glow glow-two"></div>


    <div class="login-container perfil-container">


        <!-- =========================================
             LOGO
        ========================================== -->

        <div class="login-brand">


            <?php include "../includes/auth_brand.php"; ?>


        </div>


        <!-- =========================================
             CABECERA
        ========================================== -->

        <div class="login-heading">


            <h1>
                Mi perfil
            </h1>


            <p>
                Administra la información de tu cuenta.
            </p>


        </div>


        <!-- =========================================
             INFORMACION
        ========================================== -->

        <div class="perfil-info-box">


            <div class="perfil-info-item">


                <span>
                    👤
                </span>


                <div>


                    <small>
                        Usuario
                    </small>


                    <strong>

                        <?php echo htmlspecialchars($usuario["nombre"]); ?>

                    </strong>


                </div>


            </div>



            <div class="perfil-info-item">


                <span>
                    ✉
                </span>


                <div>


                    <small>
                        Correo
                    </small>


                    <strong>

                        <?php echo htmlspecialchars($usuario["email"]); ?>

                    </strong>


                </div>


            </div>



            <div class="perfil-info-item">


                <span>
                    🔐
                </span>


                <div>


                    <small>
                        Tipo de cuenta
                    </small>


                    <strong>

                        <?php

                        echo
                            $usuario["rol"] === "admin"
                            ? "Administrador"
                            : "Usuario";

                        ?>

                    </strong>


                </div>


            </div>



            <?php if(!empty($usuario["fecha_creacion"])): ?>


                <div class="perfil-info-item">


                    <span>
                        📅
                    </span>


                    <div>


                        <small>
                            Miembro desde
                        </small>


                        <strong>

                            <?php

                            echo date(
                                "d/m/Y",
                                strtotime(
                                    $usuario["fecha_creacion"]
                                )
                            );

                            ?>

                        </strong>


                    </div>


                </div>


            <?php endif; ?>


        </div>




        <div class="profile-library-stats">
            <a href="../public/favoritos.php"><strong><?php echo (int)$resumenActividad['favoritos']; ?></strong><span>Favoritos</span></a>
            <a href="../public/historial.php"><strong><?php echo (int)$resumenActividad['vistos']; ?></strong><span>Vistos</span></a>
            <a href="../public/playlists.php"><strong><?php echo (int)$resumenActividad['playlists']; ?></strong><span>Playlists</span></a>
            <a href="../public/index.php"><strong><?php echo (int)$resumenActividad['likes']; ?></strong><span>Likes dados</span></a>
        </div>

        <!-- =========================================
             MENSAJES
        ========================================== -->

        <?php if($error): ?>


            <div class="error-login">

                ⚠

                <?php echo htmlspecialchars($error); ?>

            </div>


        <?php endif; ?>


        <?php if($mensaje): ?>


            <div class="success-login">

                ✓

                <?php echo htmlspecialchars($mensaje); ?>

            </div>


        <?php endif; ?>


        <!-- =========================================
             FORMULARIO
        ========================================== -->

        <form
        method="POST"
        class="login-form"
        >

<?php echo csrfInput(); ?>


            <div class="login-field">


                <label>
                    Nombre
                </label>


                <div class="input-group">


                    <span class="input-icon">
                        👤
                    </span>


                    <input
                    type="text"
                    name="nombre"
                    value="<?php echo htmlspecialchars($usuario["nombre"]); ?>"
                    autocomplete="name"
                    required
                    >


                </div>


            </div>



            <div class="login-field">


                <label>
                    Correo electrónico
                </label>


                <div class="input-group">


                    <span class="input-icon">
                        ✉
                    </span>


                    <input
                    type="email"
                    name="email"
                    value="<?php echo htmlspecialchars($usuario["email"]); ?>"
                    autocomplete="email"
                    required
                    >


                </div>


            </div>



            <button
            type="submit"
            class="btn-login"
            >

                Guardar cambios

                <span>
                    →
                </span>

            </button>


        </form>


        <!-- =========================================
             ACCIONES
        ========================================== -->

        <div class="perfil-actions">


            <a
            href="cambiar_password.php"
            class="perfil-action-button"
            >

                🔒 Cambiar contraseña

            </a>


            <a
            href="../public/index.php"
            class="perfil-action-button secondary"
            >

                ← Volver a DEVIOZ VIDEOS

            </a>


        </div>


    </div>


</div>


</body>

</html>