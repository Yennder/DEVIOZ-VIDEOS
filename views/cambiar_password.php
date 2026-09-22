<?php


require_once "../config/sesion.php";

verificarSesion();


require_once "../controllers/UsuarioController.php";
require_once "../controllers/ConfiguracionController.php";


$usuarioController =
    new UsuarioController();


$configController =
    new ConfiguracionController();


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


$logoSitio =
    $configController->obtener(
        "logo_sitio"
    );


if(!$logoSitio)
{

    $logoSitio =
        "logo_default.png";

}


$error = "";

$mensaje = "";


// =========================================
// CAMBIAR CONTRASEÑA
// =========================================

if($_SERVER["REQUEST_METHOD"] === "POST")
{

    verificarCsrfPost();


    $passwordActual =
        $_POST["password_actual"] ?? "";


    $passwordNueva =
        $_POST["password_nueva"] ?? "";


    $confirmarPassword =
        $_POST["confirmar_password"] ?? "";


    if(
        $passwordActual === ""
        ||
        $passwordNueva === ""
        ||
        $confirmarPassword === ""
    )
    {

        $error =
            "Complete todos los campos.";

    }
    elseif(
        $passwordNueva !==
        $confirmarPassword
    )
    {

        $error =
            "Las nuevas contraseñas no coinciden.";

    }
    else
    {

        $resultado =
            $usuarioController->cambiarPassword(
                $_SESSION["id_usuario"],
                $passwordActual,
                $passwordNueva
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
Cambiar contraseña - DEVIOZ VIDEOS
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
                Cambiar contraseña
            </h1>


            <p>
                Actualiza la contraseña de tu cuenta.
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
                    Contraseña actual
                </label>


                <div class="input-group">


                    <span class="input-icon">
                        🔒
                    </span>


                    <input
                    type="password"
                    name="password_actual"
                    id="passwordActual"
                    autocomplete="current-password"
                    required
                    >


                    <button
                    type="button"
                    class="toggle-password"
                    onclick="togglePassword('passwordActual')"
                    >

                        👁

                    </button>


                </div>


            </div>



            <div class="login-field">


                <label>
                    Nueva contraseña
                </label>


                <div class="input-group">


                    <span class="input-icon">
                        🔑
                    </span>


                    <input
                    type="password"
                    name="password_nueva"
                    id="passwordNueva"
                    placeholder="Mínimo 6 caracteres"
                    autocomplete="new-password"
                    required
                    >


                    <button
                    type="button"
                    class="toggle-password"
                    onclick="togglePassword('passwordNueva')"
                    >

                        👁

                    </button>


                </div>


            </div>



            <div class="login-field">


                <label>
                    Confirmar nueva contraseña
                </label>


                <div class="input-group">


                    <span class="input-icon">
                        🔑
                    </span>


                    <input
                    type="password"
                    name="confirmar_password"
                    id="confirmarPassword"
                    autocomplete="new-password"
                    required
                    >


                    <button
                    type="button"
                    class="toggle-password"
                    onclick="togglePassword('confirmarPassword')"
                    >

                        👁

                    </button>


                </div>


            </div>



            <button
            type="submit"
            class="btn-login"
            >

                Actualizar contraseña

                <span>
                    →
                </span>

            </button>


        </form>


        <div class="login-divider">

            <span></span>

        </div>


        <a
        href="perfil.php"
        class="back-public"
        >

            ← Volver a Mi perfil

        </a>


    </div>


</div>


<script>


function togglePassword(id)
{

    const input =
        document.getElementById(id);


    input.type =
        input.type === "password"
            ? "text"
            : "password";

}


</script>


</body>

</html>