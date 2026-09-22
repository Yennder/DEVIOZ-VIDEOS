<?php

require_once __DIR__ . "/../config/conexion.php";


class Dashboard
{

    private $conexion;


    public function __construct()
    {

        $db = new Conexion();

        $this->conexion = $db->conectar();

    }


    // =========================================
    // TOTAL VIDEOS
    // =========================================

    public function totalVideos()
    {

        $sql = "
        SELECT COUNT(*) AS total
        FROM videos
        WHERE tipo_contenido = 'video'
        ";


        $stmt = $this->conexion->prepare($sql);

        $stmt->execute();


        return $stmt->fetch(PDO::FETCH_ASSOC)["total"];

    }


    // =========================================
    // TOTAL CAPITULOS
    // =========================================

    public function totalCapitulos()
    {

        $sql = "
        SELECT COUNT(*) AS total
        FROM videos
        WHERE tipo_contenido = 'serie'
        ";


        $stmt = $this->conexion->prepare($sql);

        $stmt->execute();


        return $stmt->fetch(PDO::FETCH_ASSOC)["total"];

    }


    // =========================================
    // TOTAL SERIES
    // =========================================

    public function totalSeries()
    {

        $sql = "
        SELECT COUNT(*) AS total
        FROM series
        ";


        $stmt = $this->conexion->prepare($sql);

        $stmt->execute();


        return $stmt->fetch(PDO::FETCH_ASSOC)["total"];

    }


    // =========================================
    // TOTAL TEMPORADAS
    // =========================================

    public function totalTemporadas()
    {

        $sql = "
        SELECT COUNT(*) AS total
        FROM temporadas
        ";


        $stmt = $this->conexion->prepare($sql);

        $stmt->execute();


        return $stmt->fetch(PDO::FETCH_ASSOC)["total"];

    }


    // =========================================
    // TOTAL CATEGORIAS
    // =========================================

    public function totalCategorias()
    {

        $sql = "
        SELECT COUNT(*) AS total
        FROM categorias
        ";


        $stmt = $this->conexion->prepare($sql);

        $stmt->execute();


        return $stmt->fetch(PDO::FETCH_ASSOC)["total"];

    }


    // =========================================
    // TOTAL USUARIOS
    // =========================================

    public function totalUsuarios()
    {

        $sql = "
        SELECT COUNT(*) AS total
        FROM usuarios
        ";


        $stmt = $this->conexion->prepare($sql);

        $stmt->execute();


        return $stmt->fetch(PDO::FETCH_ASSOC)["total"];

    }


    // =========================================
    // TOTAL VISUALIZACIONES
    // =========================================

    public function totalVistas()
    {

        $sql = "
        SELECT COALESCE(SUM(vistas),0) AS total
        FROM videos
        ";


        $stmt = $this->conexion->prepare($sql);

        $stmt->execute();


        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);


        return $resultado["total"] ?? 0;

    }


    // =========================================
    // VIDEOS / CAPITULOS RECIENTES
    // =========================================

    public function videosRecientes()
    {

        $sql = "
        SELECT
            videos.*,
            categorias.nombre AS categoria
        FROM videos

        INNER JOIN categorias
        ON videos.id_categoria = categorias.id_categoria

        ORDER BY videos.fecha_publicacion DESC

        LIMIT 5
        ";


        $stmt = $this->conexion->prepare($sql);

        $stmt->execute();


        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    }


    // =========================================
    // CONTENIDO MAS VISTO
    // =========================================

    public function masVistos()
    {

        $sql = "
        SELECT
            videos.id_video,
            videos.titulo,
            videos.tipo_contenido,
            videos.vistas,
            categorias.nombre AS categoria

        FROM videos

        INNER JOIN categorias
        ON videos.id_categoria = categorias.id_categoria

        WHERE videos.estado = 'publicado'

        ORDER BY videos.vistas DESC

        LIMIT 5
        ";


        $stmt = $this->conexion->prepare($sql);

        $stmt->execute();


        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    }


    // =========================================
    // SERIES RECIENTES
    // =========================================

    public function seriesRecientes()
    {

        $sql = "
        SELECT
            id_serie,
            titulo,
            imagen_portada,
            estado,
            fecha_creacion

        FROM series

        ORDER BY fecha_creacion DESC

        LIMIT 5
        ";


        $stmt = $this->conexion->prepare($sql);

        $stmt->execute();


        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    }


    // =========================================
    // TOTAL PUBLICADOS
    // =========================================

    public function totalPublicados()
    {

        $sql = "
        SELECT COUNT(*) AS total
        FROM videos
        WHERE estado = 'publicado'
        ";


        $stmt = $this->conexion->prepare($sql);

        $stmt->execute();


        return $stmt->fetch(PDO::FETCH_ASSOC)["total"];

    }


    // =========================================
    // TOTAL BORRADORES
    // =========================================

    public function totalBorradores()
    {

        $sql = "
        SELECT COUNT(*) AS total
        FROM videos
        WHERE estado = 'borrador'
        ";


        $stmt = $this->conexion->prepare($sql);

        $stmt->execute();


        return $stmt->fetch(PDO::FETCH_ASSOC)["total"];

    }

}