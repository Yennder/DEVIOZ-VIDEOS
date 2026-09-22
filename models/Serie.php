<?php


require_once __DIR__ . "/../config/conexion.php";


class Serie
{


    private $conexion;



    public function __construct()
    {

        $db = new Conexion();

        $this->conexion = $db->conectar();

    }




    // LISTAR SERIES

    public function listar()
    {


        $sql = "

        SELECT *

        FROM series

        ORDER BY titulo ASC

        ";



        $stmt = $this->conexion->prepare($sql);


        $stmt->execute();



        return $stmt->fetchAll(PDO::FETCH_ASSOC);


    }

    // =========================================
// LISTAR SERIES CON FILTROS
// =========================================

public function listarFiltrado(
    $buscar = "",
    $estado = "",
    $orden = "recientes"
)
{

    $sql = "

    SELECT

        series.*,

        COUNT(DISTINCT temporadas.id_temporada) AS total_temporadas,

        COUNT(DISTINCT videos.id_video) AS total_capitulos

    FROM series

    LEFT JOIN temporadas
    ON series.id_serie = temporadas.id_serie

    LEFT JOIN videos
    ON series.id_serie = videos.id_serie
    AND videos.tipo_contenido = 'serie'

    WHERE 1=1

    ";


    $parametros = [];


    // BUSCAR POR TITULO

    if($buscar != "")
    {

        $sql .= "

        AND series.titulo LIKE :buscar

        ";

        $parametros[":buscar"] = "%".$buscar."%";

    }


    // FILTRAR POR ESTADO

    if($estado != "")
    {

        $sql .= "

        AND series.estado = :estado

        ";

        $parametros[":estado"] = $estado;

    }


    $sql .= "

    GROUP BY series.id_serie

    ";


    // ORDEN

    switch($orden)
    {

        case "antiguas":

            $sql .= "

            ORDER BY series.fecha_creacion ASC

            ";

        break;


        case "titulo_az":

            $sql .= "

            ORDER BY series.titulo ASC

            ";

        break;


        case "titulo_za":

            $sql .= "

            ORDER BY series.titulo DESC

            ";

        break;


        case "mas_temporadas":

            $sql .= "

            ORDER BY total_temporadas DESC

            ";

        break;


        case "mas_capitulos":

            $sql .= "

            ORDER BY total_capitulos DESC

            ";

        break;


        default:

            $sql .= "

            ORDER BY series.fecha_creacion DESC

            ";

        break;

    }


    $stmt = $this->conexion->prepare($sql);

    $stmt->execute($parametros);


    return $stmt->fetchAll(PDO::FETCH_ASSOC);

}




    // BUSCAR SERIE

    public function buscar($id)
    {


        $sql = "

        SELECT *

        FROM series

        WHERE id_serie=:id

        ";



        $stmt = $this->conexion->prepare($sql);



        $stmt->execute([

            ":id"=>$id

        ]);



        return $stmt->fetch(PDO::FETCH_ASSOC);


    }





    // CREAR SERIE

    public function crear($datos)
    {


        $sql = "

        INSERT INTO series
        (
            titulo,
            descripcion,
            imagen_portada,
            estado
        )

        VALUES
        (
            :titulo,
            :descripcion,
            :imagen,
            :estado
        )

        ";



        $stmt=$this->conexion->prepare($sql);



        return $stmt->execute([


            ":titulo"=>$datos["titulo"],


            ":descripcion"=>$datos["descripcion"],


            ":imagen"=>$datos["imagen"],


            ":estado"=>$datos["estado"]


        ]);

    }






    // ACTUALIZAR IMAGEN DE SERIE

    public function actualizarImagen($id,$imagen)
    {


        $sql="

        UPDATE series SET

        imagen_portada=:imagen

        WHERE id_serie=:id

        ";



        $stmt=$this->conexion->prepare($sql);



        return $stmt->execute([


            ":imagen"=>$imagen,


            ":id"=>$id


        ]);


    }






    // ACTUALIZAR SERIE

    public function actualizar($datos)
    {


        $sql="

        UPDATE series SET

        titulo=:titulo,

        descripcion=:descripcion,

        estado=:estado


        WHERE id_serie=:id


        ";



        $stmt=$this->conexion->prepare($sql);



        return $stmt->execute([


            ":titulo"=>$datos["titulo"],


            ":descripcion"=>$datos["descripcion"],


            ":estado"=>$datos["estado"],


            ":id"=>$datos["id"]


        ]);


    }






    // ELIMINAR SERIE

    public function eliminar($id)
    {


        $sql="

        DELETE FROM series

        WHERE id_serie=:id

        ";



        $stmt=$this->conexion->prepare($sql);



        return $stmt->execute([


            ":id"=>$id


        ]);


    }
    // SERIES PUBLICAS

public function listarPublicas()
{


    $sql="

    SELECT *

    FROM series

    WHERE estado=1

    ORDER BY titulo ASC

    ";



    $stmt=$this->conexion->prepare($sql);


    $stmt->execute();


    return $stmt->fetchAll(PDO::FETCH_ASSOC);


}

// DETALLE SERIE

public function detallePublico($id)
{


    $sql="

    SELECT *

    FROM series

    WHERE id_serie=:id

    AND estado=1

    ";



    $stmt=$this->conexion->prepare($sql);



    $stmt->execute([

        ":id"=>$id

    ]);



    return $stmt->fetch(PDO::FETCH_ASSOC);


}



}