<?php

require_once __DIR__ . "/../config/conexion.php";


class Configuracion
{

    private $conexion;


    public function __construct()
    {

        $db = new Conexion();

        $this->conexion = $db->conectar();

    }


    // =========================================
    // OBTENER VALOR POR CLAVE
    // =========================================

    public function obtener($clave)
    {

        $sql = "
        SELECT valor
        FROM configuracion
        WHERE clave = :clave
        LIMIT 1
        ";


        $stmt = $this->conexion->prepare($sql);


        $stmt->execute([
            ":clave" => $clave
        ]);


        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);


        return $resultado
            ? $resultado["valor"]
            : null;

    }


    // =========================================
    // GUARDAR / ACTUALIZAR VALOR
    // =========================================

    public function guardar($clave, $valor)
    {

        $sql = "
        INSERT INTO configuracion
        (
            clave,
            valor
        )
        VALUES
        (
            :clave,
            :valor
        )

        ON DUPLICATE KEY UPDATE
        valor = :valor_update
        ";


        $stmt = $this->conexion->prepare($sql);


        return $stmt->execute([

            ":clave" => $clave,

            ":valor" => $valor,

            ":valor_update" => $valor

        ]);

    }

}