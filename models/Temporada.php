<?php


require_once __DIR__ . "/../config/conexion.php";


class Temporada
{


    private $conexion;



    public function __construct()
    {

        $db = new Conexion();

        $this->conexion = $db->conectar();

    }





    // LISTAR TEMPORADAS DE UNA SERIE

    public function listarPorSerie($idSerie)
    {


        $sql="

        SELECT *

        FROM temporadas

        WHERE id_serie=:serie

        ORDER BY numero_temporada ASC

        ";



        $stmt=$this->conexion->prepare($sql);



        $stmt->execute([

            ":serie"=>$idSerie

        ]);



        return $stmt->fetchAll(PDO::FETCH_ASSOC);


    }





    // LISTAR TODAS LAS TEMPORADAS

    public function listar()
    {


        $sql="

        SELECT 

        temporadas.*,

        series.titulo AS serie


        FROM temporadas


        INNER JOIN series

        ON temporadas.id_serie = series.id_serie


        ORDER BY series.titulo, numero_temporada


        ";



        $stmt=$this->conexion->prepare($sql);


        $stmt->execute();



        return $stmt->fetchAll(PDO::FETCH_ASSOC);


    }





    // BUSCAR TEMPORADA

    public function buscar($id)
    {


        $sql="

        SELECT *

        FROM temporadas

        WHERE id_temporada=:id

        ";



        $stmt=$this->conexion->prepare($sql);



        $stmt->execute([

            ":id"=>$id

        ]);



        return $stmt->fetch(PDO::FETCH_ASSOC);


    }





    // CREAR TEMPORADA

    public function crear($datos)
    {


        $sql="

        INSERT INTO temporadas

        (
            id_serie,
            numero_temporada,
            titulo,
            descripcion
        )

        VALUES

        (
            :serie,
            :numero,
            :titulo,
            :descripcion
        )

        ";



        $stmt=$this->conexion->prepare($sql);



        return $stmt->execute([


            ":serie"=>$datos["serie"],


            ":numero"=>$datos["numero"],


            ":titulo"=>$datos["titulo"],


            ":descripcion"=>$datos["descripcion"]


        ]);

    }





    // ACTUALIZAR TEMPORADA

    public function actualizar($datos)
    {


        $sql="

        UPDATE temporadas SET


        numero_temporada=:numero,

        titulo=:titulo,

        descripcion=:descripcion


        WHERE id_temporada=:id


        ";



        $stmt=$this->conexion->prepare($sql);



        return $stmt->execute([


            ":numero"=>$datos["numero"],


            ":titulo"=>$datos["titulo"],


            ":descripcion"=>$datos["descripcion"],


            ":id"=>$datos["id"]


        ]);


    }





    // ELIMINAR TEMPORADA

    public function eliminar($id)
    {


        $sql="

        DELETE FROM temporadas

        WHERE id_temporada=:id

        ";



        $stmt=$this->conexion->prepare($sql);



        return $stmt->execute([

            ":id"=>$id

        ]);

    }

    // TEMPORADAS PUBLICAS

public function listarPublicas($idSerie)
{


    $sql="

    SELECT *

    FROM temporadas

    WHERE id_serie=:serie

    ORDER BY numero_temporada ASC

    ";



    $stmt=$this->conexion->prepare($sql);



    $stmt->execute([

        ":serie"=>$idSerie

    ]);



    return $stmt->fetchAll(PDO::FETCH_ASSOC);


}


// BUSCAR TEMPORADA SIGUIENTE

public function siguienteTemporada($idSerie,$numeroTemporada)
{


    $sql="

    SELECT *

    FROM temporadas

    WHERE id_serie=:serie

    AND numero_temporada > :numero


    ORDER BY numero_temporada ASC

    LIMIT 1


    ";



    $stmt=$this->conexion->prepare($sql);



    $stmt->execute([


        ":serie"=>$idSerie,


        ":numero"=>$numeroTemporada


    ]);



    return $stmt->fetch(PDO::FETCH_ASSOC);


}






// BUSCAR TEMPORADA ANTERIOR

public function anteriorTemporada($idSerie,$numeroTemporada)
{


    $sql="

    SELECT *

    FROM temporadas

    WHERE id_serie=:serie

    AND numero_temporada < :numero


    ORDER BY numero_temporada DESC

    LIMIT 1


    ";



    $stmt=$this->conexion->prepare($sql);



    $stmt->execute([


        ":serie"=>$idSerie,


        ":numero"=>$numeroTemporada


    ]);



    return $stmt->fetch(PDO::FETCH_ASSOC);


}

// =========================================
// LISTAR TEMPORADAS CON FILTROS
// =========================================

public function listarFiltrado(
    $buscar = "",
    $serie = "",
    $orden = "serie"
)
{

    $sql = "

    SELECT

        temporadas.*,

        series.titulo AS serie,

        COUNT(DISTINCT videos.id_video) AS total_capitulos

    FROM temporadas

    INNER JOIN series
    ON temporadas.id_serie = series.id_serie

    LEFT JOIN videos
    ON temporadas.id_temporada = videos.id_temporada
    AND videos.tipo_contenido = 'serie'

    WHERE 1=1

    ";


    $parametros = [];


    // BUSCAR POR TITULO O SERIE

    if($buscar != "")
    {

        $sql .= "

        AND
        (
            temporadas.titulo LIKE :buscar
            OR
            series.titulo LIKE :buscar
        )

        ";

        $parametros[":buscar"] = "%".$buscar."%";

    }


    // FILTRAR POR SERIE

    if($serie != "")
    {

        $sql .= "

        AND temporadas.id_serie = :serie

        ";

        $parametros[":serie"] = $serie;

    }


    $sql .= "

    GROUP BY temporadas.id_temporada

    ";


    switch($orden)
    {

        case "recientes":

            $sql .= "
            ORDER BY temporadas.fecha_creacion DESC
            ";

        break;


        case "antiguas":

            $sql .= "
            ORDER BY temporadas.fecha_creacion ASC
            ";

        break;


        case "numero_asc":

            $sql .= "
            ORDER BY temporadas.numero_temporada ASC
            ";

        break;


        case "numero_desc":

            $sql .= "
            ORDER BY temporadas.numero_temporada DESC
            ";

        break;


        case "mas_capitulos":

            $sql .= "
            ORDER BY total_capitulos DESC
            ";

        break;


        default:

            $sql .= "
            ORDER BY series.titulo ASC,
                     temporadas.numero_temporada ASC
            ";

        break;

    }


    $stmt = $this->conexion->prepare($sql);

    $stmt->execute($parametros);


    return $stmt->fetchAll(PDO::FETCH_ASSOC);

}



// =========================================
// SERIES PARA FILTRO
// =========================================

public function seriesFiltro()
{

    $sql = "

    SELECT
        id_serie,
        titulo

    FROM series

    ORDER BY titulo ASC

    ";


    $stmt = $this->conexion->prepare($sql);

    $stmt->execute();


    return $stmt->fetchAll(PDO::FETCH_ASSOC);

}


}