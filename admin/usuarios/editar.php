<?php


require_once "../../config/sesion.php";

verificarAdmin();


require_once "../../controllers/UsuarioController.php";


$controller = new UsuarioController();



if(!isset($_GET["id"]))
{

    header("Location: listar.php");

    exit;

}



$id = $_GET["id"];


$usuario = $controller->buscar($id);



if(!$usuario)
{

    header("Location: listar.php");

    exit;

}



$error = "";



if($_SERVER["REQUEST_METHOD"] == "POST")
{

    verificarCsrfPost();


    $nombre = trim($_POST["nombre"] ?? "");

    $email = trim($_POST["email"] ?? "");

    $rol = $_POST["rol"] ?? "usuario";

    $estado = $_POST["estado"] ?? 1;

    $password = $_POST["password"] ?? "";



    if(
        $nombre == ""
        ||
        $email == ""
    )
    {

        $error = "Nombre y correo son obligatorios.";

    }
    elseif(
        $password != ""
        &&
        strlen($password) < 6
    )
    {

        $error = "La nueva contraseña debe tener al menos 6 caracteres.";

    }
    else
    {


        $datos = [


            "id" => $id,


            "nombre" => $nombre,


            "email" => $email,


            "rol" => $rol,


            "estado" => $estado


        ];



        if($controller->actualizar($datos))
        {


            /*
            =================================
            CAMBIAR PASSWORD SOLO SI ESCRIBIÓ
            =================================
            */

            if($password != "")
            {

                $controller->actualizarPassword(
                    $id,
                    $password
                );

            }



            /*
            =================================
            SI EDITA SU PROPIA CUENTA
            ACTUALIZAMOS LA SESION
            =================================
            */

            if($id == $_SESSION["id_usuario"])
            {


                $_SESSION["nombre"] = $nombre;


                $_SESSION["rol"] = $rol;


            }



            header("Location: listar.php");

            exit;


        }
        else
        {

            $error = "No se pudo actualizar. El correo podría estar registrado por otro usuario.";

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
Editar Usuario - DEVIOZ VIDEOS
</title>


<link rel="stylesheet" href="../../assets/css/admin.css">


</head>


<body>



<?php include "../includes/sidebar.php"; ?>



<div class="admin-main">



<?php include "../includes/navbar.php"; ?>



<section class="admin-content">



<h1>
Editar Usuario
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
value="<?php echo htmlspecialchars($usuario["nombre"]); ?>"
required
>




<label>
Correo electrónico
</label>


<input
type="email"
name="email"
value="<?php echo htmlspecialchars($usuario["email"]); ?>"
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
<?php echo $usuario["rol"] == "usuario" ? "selected" : ""; ?>
>

Editor

</option>



<option
value="admin"
<?php echo $usuario["rol"] == "admin" ? "selected" : ""; ?>
>

Administrador

</option>


</select>





<label>
Estado
</label>


<select
name="estado"
<?php echo ($id == $_SESSION["id_usuario"]) ? "disabled" : ""; ?>
>


<option
value="1"
<?php echo $usuario["estado"] == 1 ? "selected" : ""; ?>
>

Activo

</option>


<option
value="0"
<?php echo $usuario["estado"] == 0 ? "selected" : ""; ?>
>

Inactivo

</option>


</select>


<?php if($id == $_SESSION["id_usuario"]): ?>

<input
type="hidden"
name="estado"
value="1"
>

<p class="form-help">
Tu propia cuenta debe permanecer activa mientras estés administrando el sistema.
</p>

<?php endif; ?>

</div>





<div class="form-section">


<h2>
Seguridad
</h2>



<label>
Nueva contraseña
</label>


<input
type="password"
name="password"
placeholder="Dejar vacío para mantener la contraseña actual"
>


<p class="form-help">

Solo completa este campo si deseas cambiar la contraseña.

</p>


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

Guardar cambios

</button>


</div>



</form>



</section>



</div>



</body>

</html>