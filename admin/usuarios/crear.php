<?php

require_once "../../config/sesion.php";

verificarAdmin();



require_once "../../controllers/UsuarioController.php";


$controller = new UsuarioController();


$error = "";

$mensaje = "";



if($_SERVER["REQUEST_METHOD"] == "POST")
{

    verificarCsrfPost();


    $nombre = trim($_POST["nombre"] ?? "");

    $email = trim($_POST["email"] ?? "");

    $password = $_POST["password"] ?? "";

    $rol = $_POST["rol"] ?? "usuario";

    $estado = $_POST["estado"] ?? 1;



    if(
        $nombre == ""
        ||
        $email == ""
        ||
        $password == ""
    )
    {

        $error = "Complete todos los campos obligatorios.";

    }
    elseif(strlen($password) < 6)
    {

        $error = "La contraseña debe tener al menos 6 caracteres.";

    }
    else
    {


        $datos = [

            "nombre" => $nombre,

            "email" => $email,

            "password" => $password,

            "rol" => $rol,

            "estado" => $estado

        ];



        if($controller->crear($datos))
        {

            header(
                "Location: listar.php"
            );

            exit;

        }
        else
        {

            $error = "No se pudo crear el usuario. El correo podría estar registrado.";

        }


    }


}


?>


<!DOCTYPE html>

<html lang="es">


<head>


<meta charset="UTF-8">


<meta name="viewport" content="width=device-width, initial-scale=1.0">


<title>
Nuevo Usuario - DEVIOZ VIDEOS
</title>


<link rel="stylesheet" href="../../assets/css/admin.css">


</head>


<body>



<?php include "../includes/sidebar.php"; ?>



<div class="admin-main">



<?php include "../includes/navbar.php"; ?>



<section class="admin-content">



<h1>
Nuevo Usuario
</h1>



<form
method="POST"
class="admin-form"
>

<?php echo csrfInput(); ?>



<div class="form-section">


<h2>
Información personal
</h2>



<label>
Nombre
</label>


<input
type="text"
name="nombre"
value="<?php echo htmlspecialchars($_POST["nombre"] ?? ""); ?>"
required
>



<label>
Correo electrónico
</label>


<input
type="email"
name="email"
value="<?php echo htmlspecialchars($_POST["email"] ?? ""); ?>"
placeholder="usuario@devioz.com"
required
>



<label>
Contraseña
</label>


<input
type="password"
name="password"
placeholder="Mínimo 6 caracteres"
required
>


</div>



<div class="form-section">


<h2>
Permisos
</h2>



<label>
Rol
</label>


<select name="rol">


<option
value="usuario"
<?php echo (($_POST["rol"] ?? "") == "usuario") ? "selected" : ""; ?>
>

Usuario

</option>



<option
value="admin"
<?php echo (($_POST["rol"] ?? "") == "admin") ? "selected" : ""; ?>
>

Administrador

</option>


</select>



<label>
Estado
</label>


<select name="estado">


<option
value="1"
<?php echo (($_POST["estado"] ?? "1") == "1") ? "selected" : ""; ?>
>

Activo

</option>



<option
value="0"
<?php echo (($_POST["estado"] ?? "") == "0") ? "selected" : ""; ?>
>

Inactivo

</option>


</select>


</div>



<?php if($error): ?>


<div class="mensaje-error">

<?php echo htmlspecialchars($error); ?>

</div>


<?php endif; ?>



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

Crear Usuario

</button>


</div>



</form>



</section>



</div>



</body>

</html>