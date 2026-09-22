<?php

require_once __DIR__ . "/../config/conexion.php";


class Categoria
{

    private $conexion;


    public function __construct()
    {

        $db = new Conexion();

        $this->conexion = $db->conectar();

    }


    // =========================================
    // LISTAR CATEGORIAS
    // =========================================

    public function listar()
    {

        $sql = "
        SELECT *
        FROM categorias
        ORDER BY id_categoria DESC
        ";


        $stmt = $this->conexion->prepare($sql);

        $stmt->execute();


        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    }


    // =========================================
    // LISTAR CATEGORIAS CON FILTROS
    // =========================================

    public function listarFiltrado(
        $buscar = "",
        $estado = "",
        $orden = "recientes"
    )
    {

        $sql = "

        SELECT

            categorias.*,

            COUNT(videos.id_video) AS total_videos

        FROM categorias

        LEFT JOIN videos
        ON categorias.id_categoria = videos.id_categoria

        WHERE 1=1

        ";


        $parametros = [];


        if($buscar != "")
        {

            $sql .= "

            AND
            (
                categorias.nombre LIKE :buscar
                OR
                categorias.descripcion LIKE :buscar
            )

            ";

            $parametros[":buscar"] = "%".$buscar."%";

        }


        if($estado != "")
        {

            $sql .= "

            AND categorias.estado = :estado

            ";

            $parametros[":estado"] = $estado;

        }


        $sql .= "

        GROUP BY categorias.id_categoria

        ";


        switch($orden)
        {

            case "antiguas":

                $sql .= "
                ORDER BY categorias.fecha_creacion ASC
                ";

            break;


            case "nombre_az":

                $sql .= "
                ORDER BY categorias.nombre ASC
                ";

            break;


            case "nombre_za":

                $sql .= "
                ORDER BY categorias.nombre DESC
                ";

            break;


            case "mas_contenido":

                $sql .= "
                ORDER BY total_videos DESC
                ";

            break;


            case "menos_contenido":

                $sql .= "
                ORDER BY total_videos ASC
                ";

            break;


            default:

                $sql .= "
                ORDER BY categorias.fecha_creacion DESC
                ";

            break;

        }


        $stmt = $this->conexion->prepare($sql);

        $stmt->execute($parametros);


        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    }


    // =========================================
    // CREAR CATEGORIA
    // =========================================

    public function crear($nombre,$descripcion)
    {

        $sql = "
        INSERT INTO categorias
        (
            nombre,
            descripcion
        )
        VALUES
        (
            :nombre,
            :descripcion
        )
        ";


        $stmt = $this->conexion->prepare($sql);


        $stmt->bindParam(":nombre",$nombre);

        $stmt->bindParam(":descripcion",$descripcion);


        return $stmt->execute();

    }


    // =========================================
    // BUSCAR POR ID
    // =========================================

    public function buscar($id)
    {

        $sql = "
        SELECT *
        FROM categorias
        WHERE id_categoria = :id
        ";


        $stmt = $this->conexion->prepare($sql);


        $stmt->bindParam(":id",$id);


        $stmt->execute();


        return $stmt->fetch(PDO::FETCH_ASSOC);

    }


    // =========================================
    // ACTUALIZAR
    // =========================================

    public function actualizar($id,$nombre,$descripcion)
    {

        $sql = "
        UPDATE categorias SET

            nombre = :nombre,

            descripcion = :descripcion

        WHERE id_categoria = :id
        ";


        $stmt = $this->conexion->prepare($sql);


        $stmt->bindParam(":nombre",$nombre);

        $stmt->bindParam(":descripcion",$descripcion);

        $stmt->bindParam(":id",$id);


        return $stmt->execute();

    }


    // =========================================
    // ELIMINAR
    // =========================================

    public function eliminar($id)
    {

        $sql = "
        DELETE FROM categorias
        WHERE id_categoria = :id
        ";


        $stmt = $this->conexion->prepare($sql);


        $stmt->bindParam(":id",$id);


        return $stmt->execute();

    }

}