<?php

require_once __DIR__ . "/../models/Usuario.php";


class UsuarioController
{

    private $modelo;


    public function __construct()
    {

        $this->modelo = new Usuario();

    }


    // =========================================
    // LOGIN
    // =========================================

    public function login($email,$password)
    {

        $usuario = $this->modelo->buscarPorEmail($email);


        if(!$usuario)
        {

            return false;

        }


        if(!password_verify($password,$usuario["password"]))
        {

            return false;

        }


        if(session_status() == PHP_SESSION_NONE)
        {

            session_start();

        }


        $_SESSION["id_usuario"] = $usuario["id_usuario"];

        $_SESSION["nombre"] = $usuario["nombre"];

        $_SESSION["rol"] = $usuario["rol"];

        session_regenerate_id(true);

        return true;

    }


    // =========================================
    // LISTAR
    // =========================================

    public function listar()
    {

        return $this->modelo->listar();

    }


    // =========================================
    // BUSCAR
    // =========================================

    public function buscar($id)
    {

        return $this->modelo->buscar($id);

    }


    // =========================================
    // CREAR
    // =========================================

    public function crear($datos)
    {

        if($this->modelo->existeEmail($datos["email"]))
        {

            return false;

        }


        return $this->modelo->crear($datos);

    }


    // =========================================
    // ACTUALIZAR
    // =========================================

    public function actualizar($datos)
    {

        if(
            $this->modelo->existeEmail(
                $datos["email"],
                $datos["id"]
            )
        )
        {

            return false;

        }


        return $this->modelo->actualizar($datos);

    }


    // =========================================
    // ACTUALIZAR CONTRASEÑA
    // =========================================

    public function actualizarPassword($id,$password)
    {

        if(empty($password))
        {

            return false;

        }


        return $this->modelo->actualizarPassword(
            $id,
            $password
        );

    }


    // =========================================
    // ELIMINAR
    // =========================================

    public function eliminar($id)
    {

        return $this->modelo->eliminar($id);

    }


    // =========================================
    // VERIFICAR ADMINISTRADOR
    // =========================================

    public function esAdministrador()
    {

        return
            isset($_SESSION["rol"])
            &&
            $_SESSION["rol"] === "admin";

    }


    // =========================================
    // CAMBIAR PASSWORD CON VALIDACION
    // =========================================

    public function cambiarPassword(
        $id,
        $passwordActual,
        $passwordNueva
    )
    {

        $usuario =
            $this->modelo->buscarConPassword(
                $id
            );


        if(!$usuario)
        {

            return [
                "ok" => false,
                "mensaje" => "Usuario no encontrado."
            ];

        }


        if(
            !password_verify(
                $passwordActual,
                $usuario["password"]
            )
        )
        {

            return [
                "ok" => false,
                "mensaje" => "La contraseña actual es incorrecta."
            ];

        }


        if(strlen($passwordNueva) < 6)
        {

            return [
                "ok" => false,
                "mensaje" => "La nueva contraseña debe tener al menos 6 caracteres."
            ];

        }


        if(
            password_verify(
                $passwordNueva,
                $usuario["password"]
            )
        )
        {

            return [
                "ok" => false,
                "mensaje" => "La nueva contraseña debe ser diferente a la actual."
            ];

        }


        $actualizado =
            $this->modelo->actualizarPassword(
                $id,
                $passwordNueva
            );


        if(!$actualizado)
        {

            return [
                "ok" => false,
                "mensaje" => "No se pudo actualizar la contraseña."
            ];

        }


        return [
            "ok" => true,
            "mensaje" => "Contraseña actualizada correctamente."
        ];

    }


    // =========================================
    // GENERAR RECUPERACION PASSWORD
    // =========================================

    public function generarRecuperacion(
        $email
    )
    {

        $usuario =
            $this->modelo->buscarPorEmail(
                $email
            );


        if(!$usuario)
        {

            return [
                "ok" => false,
                "mensaje" => "No existe una cuenta activa con ese correo."
            ];

        }


        try
        {

            $token =
                bin2hex(
                    random_bytes(32)
                );

        }
        catch(Exception $e)
        {

            return [
                "ok" => false,
                "mensaje" => "No se pudo generar el enlace de recuperación."
            ];

        }


        $fechaExpiracion =
            date(
                "Y-m-d H:i:s",
                time() + 1800
            );


        $guardado =
            $this->modelo->crearTokenRecuperacion(
                $usuario["id_usuario"],
                $token,
                $fechaExpiracion
            );


        if(!$guardado)
        {

            return [
                "ok" => false,
                "mensaje" => "No se pudo generar la recuperación."
            ];

        }


        return [

            "ok" => true,

            "token" => $token,

            "mensaje" =>
                "Se generó correctamente el enlace de recuperación."

        ];

    }


    // =========================================
    // RESTABLECER PASSWORD
    // =========================================

    public function restablecerPassword(
        $token,
        $passwordNueva
    )
    {

        $recuperacion =
            $this->modelo->buscarTokenRecuperacion(
                $token
            );


        if(!$recuperacion)
        {

            return [
                "ok" => false,
                "mensaje" => "El enlace es inválido o ha expirado."
            ];

        }


        if(strlen($passwordNueva) < 6)
        {

            return [
                "ok" => false,
                "mensaje" => "La contraseña debe tener al menos 6 caracteres."
            ];

        }


        $actualizado =
            $this->modelo->actualizarPassword(
                $recuperacion["id_usuario"],
                $passwordNueva
            );


        if(!$actualizado)
        {

            return [
                "ok" => false,
                "mensaje" => "No se pudo actualizar la contraseña."
            ];

        }


        $this->modelo->marcarTokenUsado(
            $recuperacion["id_recuperacion"]
        );


        return [
            "ok" => true,
            "mensaje" => "Contraseña actualizada correctamente."
        ];

    }

}