<?php


require_once "../config/sesion.php";



require_once "../controllers/UsuarioController.php";
require_once "../controllers/ConfiguracionController.php";


$controller =
    new UsuarioController();


$configController =
    new ConfiguracionController();


$error = "";



// =========================================
// REDIRECCION DESPUES DEL REGISTRO
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
// PROCESAR REGISTRO
// =========================================

if($_SERVER["REQUEST_METHOD"] == "POST")
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


    $password =
        $_POST["password"] ?? "";


    $confirmarPassword =
        $_POST["confirmar_password"] ?? "";



    // =========================================
    // VALIDACIONES
    // =========================================

    if(
        $nombre === ""
        ||
        $email === ""
        ||
        $password === ""
        ||
        $confirmarPassword === ""
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
    elseif(strlen($password) < 6)
    {

        $error =
            "La contraseña debe tener al menos 6 caracteres.";

    }
    elseif($password !== $confirmarPassword)
    {

        $error =
            "Las contraseñas no coinciden.";

    }
    else
    {


        // =========================================
        // DATOS DEL USUARIO PUBLICO
        // =========================================

        $datos = [


            "nombre" =>
                $nombre,


            "email" =>
                $email,


            "password" =>
                $password,


            "rol" =>
                "usuario",


            "estado" =>
                1


        ];



        if(
            $controller->crear(
                $datos
            )
        )
        {


            // =========================================
            // LOGIN AUTOMATICO
            // =========================================

            if(
                $controller->login(
                    $email,
                    $password
                )
            )
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


                exit;

            }



            // =========================================
            // SI FALLA LOGIN AUTOMATICO
            // =========================================

            $urlLogin =
                "login.php";


            if($redirect)
            {

                $urlLogin .=
                    "?redirect="
                    .
                    urlencode(
                        $redirect
                    );

            }


            header(
                "Location: "
                .
                $urlLogin
            );


            exit;

        }
        else
        {

            $error =
                "No se pudo crear la cuenta. El correo podría estar registrado.";

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
Crear cuenta - DEVIOZ VIDEOS
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
                Crear cuenta
            </h1>


            <p>
                Regístrate para acceder a las funciones inteligentes de DEVIOZ VIDEOS.
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
                    Nombre completo
                </label>


                <div class="input-group">


                    <span class="input-icon">
                        👤
                    </span>


                    <input
                    type="text"
                    name="nombre"
                    placeholder="Tu nombre"
                    value="<?php echo htmlspecialchars($_POST["nombre"] ?? ""); ?>"
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
                    id="registroPassword"
                    placeholder="Mínimo 6 caracteres"
                    autocomplete="new-password"
                    required
                    >


                    <button
                    type="button"
                    class="toggle-password"
                    onclick="mostrarRegistroPassword()"
                    aria-label="Mostrar u ocultar contraseña"
                    >

                        👁

                    </button>


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
                    id="registroConfirmarPassword"
                    placeholder="Repite tu contraseña"
                    autocomplete="new-password"
                    required
                    >


                    <button
                    type="button"
                    class="toggle-password"
                    onclick="mostrarRegistroConfirmarPassword()"
                    aria-label="Mostrar u ocultar contraseña"
                    >

                        👁

                    </button>


                </div>


            </div>



            <button
            type="submit"
            class="btn-login"
            >

                Crear cuenta

                <span>
                    →
                </span>

            </button>


        </form>



        <div class="login-divider">

            <span></span>

        </div>



        <div class="registro-login-link">


            ¿Ya tienes una cuenta?


            <a
            href="login.php<?php echo $redirect ? '?redirect=' . urlencode($redirect) : ''; ?>"
            >

                Iniciar sesión

            </a>


        </div>



        <a
        href="../public/index.php"
        class="back-public"
        >

            ← Volver a DEVIOZ VIDEOS

        </a>



        <div class="login-footer">

            Al crear una cuenta accederás como usuario de DEVIOZ VIDEOS.

        </div>


    </div>


</div>



<script>


function mostrarRegistroPassword()
{

    const input =
        document.getElementById(
            "registroPassword"
        );


    input.type =
        input.type === "password"
            ? "text"
            : "password";

}



function mostrarRegistroConfirmarPassword()
{

    const input =
        document.getElementById(
            "registroConfirmarPassword"
        );


    input.type =
        input.type === "password"
            ? "text"
            : "password";

}


</script>


</body>

</html>