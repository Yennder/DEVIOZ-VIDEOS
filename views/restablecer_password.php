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

$token =
    $_GET["token"]
    ??
    $_POST["token"]
    ??
    "";



$logoSitio =
    $configController->obtener(
        "logo_sitio"
    );


if(!$logoSitio)
{

    $logoSitio =
        "logo_default.png";

}



if($token === "")
{

    $error =
        "El enlace de recuperación es inválido.";

}



if(
    $_SERVER["REQUEST_METHOD"] === "POST"
    &&
    !$error
)
{

    verificarCsrfPost();


    $password =
        $_POST["password"] ?? "";


    $confirmarPassword =
        $_POST["confirmar_password"] ?? "";


    if(
        $password === ""
        ||
        $confirmarPassword === ""
    )
    {

        $error =
            "Complete todos los campos.";

    }
    elseif(
        $password !==
        $confirmarPassword
    )
    {

        $error =
            "Las contraseñas no coinciden.";

    }
    else
    {

        $resultado =
            $controller->restablecerPassword(
                $token,
                $password
            );


        if($resultado["ok"])
        {

            $mensaje =
                $resultado["mensaje"];

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
Nueva contraseña - DEVIOZ VIDEOS
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
                Nueva contraseña
            </h1>


            <p>
                Define una nueva contraseña para tu cuenta.
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


        <?php if(!$mensaje): ?>


            <form
            method="POST"
            class="login-form"
            >

<?php echo csrfInput(); ?>


                <input
                type="hidden"
                name="token"
                value="<?php echo htmlspecialchars($token); ?>"
                >


                <div class="login-field">


                    <label>
                        Nueva contraseña
                    </label>


                    <div class="input-group">


                        <span class="input-icon">
                            🔒
                        </span>


                        <input
                        type="password"
                        name="password"
                        id="recoveryPassword"
                        placeholder="Mínimo 6 caracteres"
                        required
                        >


                    </div>


                </div>


                <div class="login-field">


                    <label>
                        Confirmar contraseña
                    </label>


                    <div class="input-group">


                        <span class="input-icon">
                            🔒
                        </span>


                        <input
                        type="password"
                        name="confirmar_password"
                        id="recoveryConfirmPassword"
                        required
                        >


                    </div>


                </div>


                <button
                type="submit"
                class="btn-login"
                >

                    Guardar nueva contraseña

                    <span>
                        →
                    </span>

                </button>


            </form>


        <?php else: ?>


            <a
            href="login.php"
            class="btn-login"
            >

                Ir a iniciar sesión

            </a>


        <?php endif; ?>


    </div>


</div>


</body>

</html>