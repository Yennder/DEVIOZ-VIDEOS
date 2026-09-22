<?php

require_once __DIR__ . "/../config/conexion.php";


class Usuario
{

    private $conexion;


    public function __construct()
    {

        $db = new Conexion();

        $this->conexion = $db->conectar();

    }


    // =========================================
    // LOGIN - BUSCAR POR EMAIL
    // =========================================

    public function buscarPorEmail($email)
    {

        $sql = "
        SELECT *
        FROM usuarios
        WHERE email = :email
        AND estado = 1
        LIMIT 1
        ";


        $stmt = $this->conexion->prepare($sql);


        $stmt->execute([

            ":email" => $email

        ]);


        return $stmt->fetch(PDO::FETCH_ASSOC);

    }


    // =========================================
    // LISTAR USUARIOS
    // =========================================

    public function listar()
    {

        $sql = "
        SELECT
            id_usuario,
            nombre,
            email,
            rol,
            estado,
            fecha_creacion
        FROM usuarios
        ORDER BY id_usuario DESC
        ";


        $stmt = $this->conexion->prepare($sql);

        $stmt->execute();


        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    }


    // =========================================
    // BUSCAR USUARIO POR ID
    // =========================================

    public function buscar($id)
    {

        $sql = "
        SELECT
            id_usuario,
            nombre,
            email,
            rol,
            estado,
            fecha_creacion
        FROM usuarios
        WHERE id_usuario = :id
        ";


        $stmt = $this->conexion->prepare($sql);


        $stmt->execute([

            ":id" => $id

        ]);


        return $stmt->fetch(PDO::FETCH_ASSOC);

    }


    // =========================================
    // CREAR USUARIO
    // =========================================

    public function crear($datos)
    {

        $sql = "
        INSERT INTO usuarios
        (
            nombre,
            email,
            password,
            rol,
            estado
        )
        VALUES
        (
            :nombre,
            :email,
            :password,
            :rol,
            :estado
        )
        ";


        $stmt = $this->conexion->prepare($sql);


        return $stmt->execute([

            ":nombre" => $datos["nombre"],

            ":email" => $datos["email"],

            ":password" => password_hash(
                $datos["password"],
                PASSWORD_DEFAULT
            ),

            ":rol" => $datos["rol"],

            ":estado" => $datos["estado"]

        ]);

    }


    // =========================================
    // ACTUALIZAR USUARIO
    // =========================================

    public function actualizar($datos)
    {

        $sql = "
        UPDATE usuarios SET

            nombre = :nombre,

            email = :email,

            rol = :rol,

            estado = :estado

        WHERE id_usuario = :id
        ";


        $stmt = $this->conexion->prepare($sql);


        return $stmt->execute([

            ":nombre" => $datos["nombre"],

            ":email" => $datos["email"],

            ":rol" => $datos["rol"],

            ":estado" => $datos["estado"],

            ":id" => $datos["id"]

        ]);

    }


    // =========================================
    // CAMBIAR CONTRASEÑA
    // =========================================

    public function actualizarPassword($id, $password)
    {

        $sql = "
        UPDATE usuarios SET

            password = :password

        WHERE id_usuario = :id
        ";


        $stmt = $this->conexion->prepare($sql);


        return $stmt->execute([

            ":password" => password_hash(
                $password,
                PASSWORD_DEFAULT
            ),

            ":id" => $id

        ]);

    }


    // =========================================
    // VERIFICAR EMAIL DUPLICADO
    // =========================================

    public function existeEmail($email, $idExcluir = null)
    {

        $sql = "
        SELECT COUNT(*)
        FROM usuarios
        WHERE email = :email
        ";


        $parametros = [

            ":email" => $email

        ];


        if($idExcluir !== null)
        {

            $sql .= "
            AND id_usuario != :id
            ";

            $parametros[":id"] = $idExcluir;

        }


        $stmt = $this->conexion->prepare($sql);

        $stmt->execute($parametros);


        return $stmt->fetchColumn() > 0;

    }


    // =========================================
    // ELIMINAR USUARIO
    // =========================================

    public function eliminar($id)
    {

        $sql = "
        DELETE FROM usuarios
        WHERE id_usuario = :id
        ";


        $stmt = $this->conexion->prepare($sql);


        return $stmt->execute([

            ":id" => $id

        ]);

    }

    // =========================================
// BUSCAR USUARIO CON PASSWORD
// =========================================

public function buscarConPassword($id)
{

    $sql = "
    SELECT
        id_usuario,
        nombre,
        email,
        password,
        rol,
        estado
    FROM usuarios
    WHERE id_usuario = :id
    LIMIT 1
    ";


    $stmt = $this->conexion->prepare($sql);


    $stmt->execute([

        ":id" => $id

    ]);


    return $stmt->fetch(PDO::FETCH_ASSOC);

}

// =========================================
// CREAR TOKEN DE RECUPERACION
// =========================================

public function crearTokenRecuperacion(
    $idUsuario,
    $token,
    $fechaExpiracion
)
{

    $sql = "
    INSERT INTO recuperacion_password
    (
        id_usuario,
        token,
        fecha_expiracion,
        usado
    )
    VALUES
    (
        :id_usuario,
        :token,
        :fecha_expiracion,
        0
    )
    ";


    $stmt = $this->conexion->prepare($sql);


    return $stmt->execute([

        ":id_usuario" =>
            $idUsuario,

        ":token" =>
            $token,

        ":fecha_expiracion" =>
            $fechaExpiracion

    ]);

}


// =========================================
// BUSCAR TOKEN VALIDO
// =========================================

public function buscarTokenRecuperacion(
    $token
)
{

    $sql = "
    SELECT
        r.id_recuperacion,
        r.id_usuario,
        r.token,
        r.fecha_expiracion,
        r.usado,
        u.email,
        u.nombre
    FROM recuperacion_password r
    INNER JOIN usuarios u
        ON u.id_usuario = r.id_usuario
    WHERE r.token = :token
    AND r.usado = 0
    AND r.fecha_expiracion >= NOW()
    LIMIT 1
    ";


    $stmt = $this->conexion->prepare($sql);


    $stmt->execute([

        ":token" =>
            $token

    ]);


    return $stmt->fetch(PDO::FETCH_ASSOC);

}


// =========================================
// MARCAR TOKEN COMO USADO
// =========================================

public function marcarTokenUsado(
    $idRecuperacion
)
{

    $sql = "
    UPDATE recuperacion_password
    SET usado = 1
    WHERE id_recuperacion = :id
    ";


    $stmt = $this->conexion->prepare($sql);


    return $stmt->execute([

        ":id" =>
            $idRecuperacion

    ]);

}

}