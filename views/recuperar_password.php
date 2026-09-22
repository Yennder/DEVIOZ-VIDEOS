<?php


require_once "../config/sesion.php";


require_once "../controllers/UsuarioController.php";
require_once "../controllers/ConfiguracionController.php";


$controller =
    new UsuarioController();


$configController =
    new ConfiguracionController();


$error = "";

$mensaje = "";

$urlRecuperacion = "";



$logoSitio =
    $configController->obtener(
        "logo_sitio"
    );


if(!$logoSitio)
{

    $logoSitio =
        "logo_default.png";

}



if($_SERVER["REQUEST_METHOD"] === "POST")
{

    verificarCsrfPost();


    $email =
        trim(
            $_POST["email"] ?? ""
        );


    if($email === "")
    {

        $error =
            "Ingrese su correo electrónico.";

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

        $resultado =
            $controller->generarRecuperacion(
                $email
            );


        if($resultado["ok"])
        {

            $mensaje =
                $resultado["mensaje"];


            $urlRecuperacion =
                "restablecer_password.php?token="
                .
                urlencode(
                    $resultado["token"]
                );

        }
        else
        {

            $error =
                $resultado["mensaje"];

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
Recuperar contraseña - DEVIOZ VIDEOS
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


    <div class="login-container">


        <div class="login-brand">


            <?php include "../includes/auth_brand.php"; ?>


        </div>


        <div class="login-heading">


            <h1>
                Recuperar contraseña
            </h1>


            <p>
                Ingresa el correo asociado a tu cuenta.
            </p>


        </div>


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


        <form
        method="POST"
        class="login-form"
        >

<?php echo csrfInput(); ?>


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
                    placeholder="correo@ejemplo.com"
                    value="<?php echo htmlspecialchars($_POST["email"] ?? ""); ?>"
                    required
                    >


                </div>


            </div>


            <button
            type="submit"
            class="btn-login"
            >

                Generar recuperación

                <span>
                    →
                </span>

            </button>


        </form>


        <?php if($urlRecuperacion): ?>


            <div class="login-divider">

                <span></span>

            </div>


            <div class="recovery-demo-link">


                <p>
                    Modo local:
                </p>


                <a
                href="<?php echo htmlspecialchars($urlRecuperacion); ?>"
                >

                    Restablecer contraseña

                </a>


            </div>


        <?php endif; ?>


        <div class="login-divider">

            <span></span>

        </div>


        <a
        href="login.php"
        class="back-public"
        >

            ← Volver al login

        </a>


    </div>


</div>


</body>

</html>