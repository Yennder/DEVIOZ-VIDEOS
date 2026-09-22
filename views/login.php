<?php


require_once "../config/sesion.php";



require_once "../controllers/UsuarioController.php";
require_once "../controllers/ConfiguracionController.php";


$error = "";



// =========================================
// REDIRECCION DESPUES DEL LOGIN
// =========================================

$redirect =
    $_GET["redirect"]
    ??
    $_POST["redirect"]
    ??
    "";



// =========================================
// VALIDAR REDIRECCION INTERNA
// =========================================

if(
    $redirect
    &&
    !rutaDeviozInternaValida((string)$redirect)
)
{

    $redirect = "";

}



// =========================================
// SI YA ESTA AUTENTICADO
// =========================================

if(isset($_SESSION["id_usuario"]))
{


    if(
        isset($_SESSION["rol"])
        &&
        $_SESSION["rol"] === "admin"
    )
    {

        header(
            "Location: ../admin/dashboard.php"
        );

    }
    else
    {

        if($redirect)
        {

            header(
                "Location: "
                .
                $redirect
            );

        }
        else
        {

            header(
                "Location: ../public/index.php"
            );

        }

    }


    exit;

}



// =========================================
// LOGO
// =========================================

$configController =
    new ConfiguracionController();


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
// PROCESAR LOGIN
// =========================================

if($_SERVER["REQUEST_METHOD"] == "POST")
{

    verificarCsrfPost();


    $email =
        trim(
            $_POST["email"] ?? ""
        );


    $password =
        $_POST["password"] ?? "";


    $usuario =
        new UsuarioController();



    if(
        $usuario->login(
            $email,
            $password
        )
    )
    {


        // =========================================
        // ADMINISTRADOR
        // =========================================

        if(
            isset($_SESSION["rol"])
            &&
            $_SESSION["rol"] === "admin"
        )
        {

            header(
                "Location: ../admin/dashboard.php"
            );


            exit;

        }



        // =========================================
        // USUARIO NORMAL
        // =========================================

        if($redirect)
        {

            header(
                "Location: "
                .
                $redirect
            );

        }
        else
        {

            header(
                "Location: ../public/index.php"
            );

        }


        exit;

    }
    else
    {

        $error =
            "Correo o contraseña incorrectos";

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
Login - DEVIOZ VIDEOS
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
                Bienvenido
            </h1>


            <p>
                Inicia sesión para acceder a DEVIOZ VIDEOS
            </p>


        </div>



        <!-- =========================================
             ERROR
        ========================================== -->

        <?php if($error): ?>


            <div class="error-login">


                <span>
                    ⚠
                </span>


                <?php echo htmlspecialchars($error); ?>


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


            <?php if($redirect): ?>


                <input
                type="hidden"
                name="redirect"
                value="<?php echo htmlspecialchars($redirect); ?>"
                >


            <?php endif; ?>



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
                    autocomplete="email"
                    required
                    >


                </div>


            </div>



            <div class="login-field">


                <label>
                    Contraseña
                </label>


                <div class="input-group">


                    <span class="input-icon">
                        🔒
                    </span>


                    <input
                    type="password"
                    name="password"
                    id="password"
                    placeholder="Ingresa tu contraseña"
                    autocomplete="current-password"
                    required
                    >


                    <button
                    type="button"
                    class="toggle-password"
                    onclick="mostrarPassword()"
                    aria-label="Mostrar u ocultar contraseña"
                    >

                        👁

                    </button>


                </div>


            </div>

            <div class="forgot-password-link">

    <a href="recuperar_password.php">

        ¿Olvidaste tu contraseña?

    </a>

</div>


            <button
            type="submit"
            class="btn-login"
            >

                Iniciar sesión

                <span>
                    →
                </span>

            </button>


        </form>



        <div class="login-divider">

            <span></span>

        </div>



        <div class="registro-login-link">


            ¿No tienes una cuenta?


            <a
            href="registro.php<?php echo $redirect ? '?redirect=' . urlencode($redirect) : ''; ?>"
            >

                Crear cuenta

            </a>


        </div>



        <a
        href="../public/index.php"
        class="back-public"
        >

            ← Volver a DEVIOZ VIDEOS

        </a>



        <div class="login-footer">

            Acceso para usuarios registrados

        </div>


    </div>


</div>



<script>


function mostrarPassword()
{

    const password =
        document.getElementById(
            "password"
        );


    if(password.type === "password")
    {

        password.type =
            "text";

    }
    else
    {

        password.type =
            "password";

    }

}


</script>


</body>

</html>