<?php

require_once __DIR__ . "/../config/conexion.php";


class Video
{
    /** Guarda las etiquetas en la misma transaccion que el video. */
    private function sincronizarGenerosVideo(int $idVideo, array $seleccionados): void
    {
        if (count($seleccionados) > 40) throw new InvalidArgumentException('Demasiados generos');
        $ids = [];
        foreach ($seleccionados as $id) {
            if (!is_scalar($id) || !ctype_digit((string)$id) || (int)$id <= 0) {
                throw new InvalidArgumentException('Genero no valido');
            }
            $ids[] = (int)$id;
        }
        $ids = array_values(array_unique($ids));
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $st = $this->conexion->prepare("SELECT id_genero FROM generos WHERE estado=1 AND id_genero IN ($placeholders)");
            $st->execute($ids);
            if (count($st->fetchAll(PDO::FETCH_COLUMN)) !== count($ids)) {
                throw new InvalidArgumentException('Uno de los generos seleccionados no esta activo');
            }
        }
        $st = $this->conexion->prepare('DELETE FROM video_generos WHERE id_video = :id');
        $st->execute([':id'=>$idVideo]);
        if ($ids) {
            $insert = $this->conexion->prepare('INSERT INTO video_generos (id_video, id_genero) VALUES (:video,:genero)');
            foreach ($ids as $generoId) $insert->execute([':video'=>$idVideo, ':genero'=>$generoId]);
        }
    }



    private $conexion;


    public function __construct()
    {

        $db = new Conexion();

        $this->conexion = $db->conectar();

    }

// BUSCAR VIDEOS PUBLICOS

public function buscarPublicos($texto = "", $categoria = "", $orden = "recientes", $genero = "")
{
    $sql = "
        SELECT
            videos.*,
            categorias.nombre AS categoria,
            (SELECT COUNT(*) FROM likes WHERE likes.id_video = videos.id_video) AS likes,
            (SELECT COALESCE(ROUND(AVG(vr.estrellas), 1), 0) FROM video_valoraciones vr WHERE vr.id_video = videos.id_video) AS valoracion_promedio,
            (SELECT COUNT(*) FROM video_valoraciones vr WHERE vr.id_video = videos.id_video) AS valoracion_total
        FROM videos
        INNER JOIN categorias ON videos.id_categoria = categorias.id_categoria
        WHERE videos.estado = 'publicado'
    ";

    $parametros = [];

    if ($texto !== "")
    {
        $sql .= " AND (videos.titulo LIKE :texto_titulo OR videos.descripcion LIKE :texto_descripcion) ";
        $parametros[":texto_titulo"] = "%" . $texto . "%";
        $parametros[":texto_descripcion"] = "%" . $texto . "%";
    }

    if ($categoria !== "")
    {
        $sql .= " AND videos.id_categoria = :categoria ";
        $parametros[":categoria"] = $categoria;
    }

    if ($genero !== "")
    {
        $sql .= " AND EXISTS (SELECT 1 FROM video_generos vg
                  INNER JOIN generos g ON g.id_genero=vg.id_genero
                  WHERE vg.id_video=videos.id_video AND vg.id_genero=:genero AND g.estado=1) ";
        $parametros[":genero"] = (int)$genero;
    }

    switch ($orden)
    {
        case "popular":
            $sql .= " ORDER BY videos.vistas DESC, likes DESC, videos.fecha_publicacion DESC ";
            break;
        case "antiguos":
            $sql .= " ORDER BY videos.fecha_publicacion ASC ";
            break;
        case "titulo":
            $sql .= " ORDER BY videos.titulo ASC ";
            break;
        default:
            $sql .= " ORDER BY videos.fecha_publicacion DESC ";
            break;
    }

    $stmt = $this->conexion->prepare($sql);
    $stmt->execute($parametros);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

    // LISTAR VIDEOS

    public function listar()
    {

        $sql = "
        SELECT 
            videos.*,
            categorias.nombre AS categoria,
            usuarios.nombre AS usuario

        FROM videos

        INNER JOIN categorias
        ON videos.id_categoria = categorias.id_categoria

        INNER JOIN usuarios
        ON videos.id_usuario = usuarios.id_usuario

        ORDER BY id_video DESC
        ";


        $stmt = $this->conexion->prepare($sql);

        $stmt->execute();


        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    }
    // =========================================
// LISTAR VIDEOS CON FILTROS
// =========================================

public function listarFiltrado(
    $buscar = "",
    $categoria = "",
    $tipo = "",
    $estado = "",
    $orden = "recientes"
)
{

    $sql = "
    SELECT
        videos.*,
        categorias.nombre AS categoria,
        usuarios.nombre AS usuario

    FROM videos

    INNER JOIN categorias
    ON videos.id_categoria = categorias.id_categoria

    INNER JOIN usuarios
    ON videos.id_usuario = usuarios.id_usuario

    WHERE 1=1
    ";


    $parametros = [];


    // BUSCAR POR TITULO
    if($buscar != "")
    {

        $sql .= "
        AND videos.titulo LIKE :buscar
        ";

        $parametros[":buscar"] = "%".$buscar."%";

    }


    // FILTRAR POR CATEGORIA
    if($categoria != "")
    {

        $sql .= "
        AND videos.id_categoria = :categoria
        ";

        $parametros[":categoria"] = $categoria;

    }


    // FILTRAR POR TIPO
    if($tipo != "")
    {

        $sql .= "
        AND videos.tipo_contenido = :tipo
        ";

        $parametros[":tipo"] = $tipo;

    }


    // FILTRAR POR ESTADO
    if($estado != "")
    {

        $sql .= "
        AND videos.estado = :estado
        ";

        $parametros[":estado"] = $estado;

    }


    // ORDEN
    switch($orden)
    {

        case "antiguos":

            $sql .= "
            ORDER BY videos.fecha_publicacion ASC
            ";

        break;


        case "mas_vistos":

            $sql .= "
            ORDER BY videos.vistas DESC
            ";

        break;


        case "menos_vistos":

            $sql .= "
            ORDER BY videos.vistas ASC
            ";

        break;


        case "titulo_az":

            $sql .= "
            ORDER BY videos.titulo ASC
            ";

        break;


        case "titulo_za":

            $sql .= "
            ORDER BY videos.titulo DESC
            ";

        break;


        default:

            $sql .= "
            ORDER BY videos.fecha_publicacion DESC
            ";

        break;

    }


    $stmt = $this->conexion->prepare($sql);

    $stmt->execute($parametros);


    return $stmt->fetchAll(PDO::FETCH_ASSOC);

}

    // CATEGORIAS PUBLICAS

public function listarCategorias()
{

    $sql = "

    SELECT *

    FROM categorias

    WHERE estado=1

    ORDER BY FIELD(nombre, 'Serie', 'Películas', 'Documentales', 'Laboratorio', 'Educacional') = 0,
             FIELD(nombre, 'Serie', 'Películas', 'Documentales', 'Laboratorio', 'Educacional'), nombre

    ";


    $stmt = $this->conexion->prepare($sql);


    $stmt->execute();


    return $stmt->fetchAll(PDO::FETCH_ASSOC);

}



    // LISTAR CATEGORIAS PARA FORMULARIO

    public function categorias()
    {

        $sql = "SELECT * FROM categorias
                WHERE estado=1
                ORDER BY FIELD(nombre, 'Serie', 'Películas', 'Documentales', 'Laboratorio', 'Educacional') = 0,
                         FIELD(nombre, 'Serie', 'Películas', 'Documentales', 'Laboratorio', 'Educacional'), nombre";


        $stmt = $this->conexion->prepare($sql);

        $stmt->execute();


        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    }




    // CREAR VIDEO

public function crear($datos)
{


    $sql = "

    INSERT INTO videos

    (

        id_categoria,

        id_usuario,

        titulo,

        descripcion,

        archivo_video,

        miniatura,

        estado,

        tipo_contenido,

        id_serie,

        id_temporada,

        numero_capitulo

    )


    VALUES

    (

        :categoria,

        :usuario,

        :titulo,

        :descripcion,

        :archivo,

        :miniatura,

        :estado,

        :tipo,

        :serie,

        :temporada,

        :capitulo

    )

    ";



    $stmt = $this->conexion->prepare($sql);



    try {
    $this->conexion->beginTransaction();
    $registrado = $stmt->execute([



        ":categoria"=>$datos["categoria"],



        ":usuario"=>$datos["usuario"],



        ":titulo"=>$datos["titulo"],



        ":descripcion"=>$datos["descripcion"],



        ":archivo"=>$datos["archivo"],



        ":miniatura"=>$datos["miniatura"],



        ":estado"=>$datos["estado"],



        ":tipo"=>$datos["tipo_contenido"],



        ":serie" => $datos["id_serie"] ?? null,

":temporada" => $datos["id_temporada"] ?? null,

":capitulo" => $datos["numero_capitulo"] ?? null


    ]);
    if (!$registrado) throw new RuntimeException('No se pudo insertar el video');
    $this->sincronizarGenerosVideo((int)$this->conexion->lastInsertId(), $datos['generos'] ?? []);
    $this->conexion->commit();
    return true;
    } catch (Throwable $e) {
        if ($this->conexion->inTransaction()) $this->conexion->rollBack();
        error_log('DEVIOZ: no se pudo guardar video y generos: '.$e->getMessage());
        return false;
    }

}

// DETALLE VIDEO PUBLICO

public function buscarDetalle($id)
{


    $sql = "

    SELECT

    videos.*,

    categorias.nombre AS categoria,

    usuarios.nombre AS usuario,

    series.titulo AS serie,

    temporadas.numero_temporada


    FROM videos


    INNER JOIN categorias

    ON videos.id_categoria = categorias.id_categoria



    INNER JOIN usuarios

    ON videos.id_usuario = usuarios.id_usuario



    LEFT JOIN series

    ON videos.id_serie = series.id_serie



    LEFT JOIN temporadas

    ON videos.id_temporada = temporadas.id_temporada



    WHERE videos.id_video = :id

    AND videos.estado='publicado'


    ";



    $stmt=$this->conexion->prepare($sql);



    $stmt->execute([

        ":id"=>$id

    ]);



    return $stmt->fetch(PDO::FETCH_ASSOC);


}

// AUMENTAR VISTAS

public function aumentarVista($id)
{


    $sql = "

    UPDATE videos

    SET vistas = vistas + 1

    WHERE id_video = :id

    ";


    $stmt = $this->conexion->prepare($sql);


    $stmt->bindParam(
        ":id",
        $id
    );


    return $stmt->execute();


}

// VIDEOS RELACIONADOS

public function relacionados($categoria,$id)
{
    // Orden determinista y solo videos independientes para evitar saltos o bucles.
    $sql = "
        SELECT *
        FROM videos
        WHERE id_categoria = :categoria
          AND id_video <> :id
          AND tipo_contenido = 'video'
          AND estado = 'publicado'
        ORDER BY fecha_publicacion DESC, id_video DESC
        LIMIT 20
    ";

    $stmt = $this->conexion->prepare($sql);
    $stmt->execute([
        ':categoria' => $categoria,
        ':id' => $id,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// BUSCAR VIDEO POR ID

public function buscar($id)
{

    $sql = "
    SELECT *
    FROM videos
    WHERE id_video = :id
    ";


    $stmt = $this->conexion->prepare($sql);


    $stmt->bindParam(
        ":id",
        $id
    );


    $stmt->execute();


    return $stmt->fetch(PDO::FETCH_ASSOC);

}




// ACTUALIZAR VIDEO

// ACTUALIZAR VIDEO

public function actualizar($datos)
{


    $sql = "

    UPDATE videos SET


        id_categoria = :categoria,


        titulo = :titulo,


        descripcion = :descripcion,


        archivo_video = :archivo,


        miniatura = :miniatura,


        estado = :estado,


        tipo_contenido = :tipo,


        id_serie = :serie,


        id_temporada = :temporada,


        numero_capitulo = :capitulo



    WHERE id_video = :id


    ";



    $stmt = $this->conexion->prepare($sql);



    try {
    $this->conexion->beginTransaction();
    $actualizado = $stmt->execute([



        ":categoria" => $datos["categoria"],


        ":titulo" => $datos["titulo"],


        ":descripcion" => $datos["descripcion"],


        ":archivo" => $datos["archivo"],


        ":miniatura" => $datos["miniatura"],


        ":estado" => $datos["estado"],


       ":tipo" => $datos["tipo_contenido"] ?? null,


        ":serie" => $datos["id_serie"] ?? null,


        ":temporada" => $datos["id_temporada"] ?? null,


        ":capitulo" => $datos["numero_capitulo"] ?? null,


        ":id" => $datos["id"]



    ]);
    if (!$actualizado) throw new RuntimeException('No se pudo actualizar el video');
    $this->sincronizarGenerosVideo((int)$datos['id'], $datos['generos'] ?? []);
    $this->conexion->commit();
    return true;
    } catch (Throwable $e) {
        if ($this->conexion->inTransaction()) $this->conexion->rollBack();
        error_log('DEVIOZ: no se pudo actualizar video y generos: '.$e->getMessage());
        return false;
    }

}

// ELIMINAR VIDEO

public function eliminar($id)
{


    // Primero obtenemos archivos

    $video = $this->buscar($id);



    if($video)
    {


        $rutaVideo = __DIR__ . "/../uploads/videos/" . $video["archivo_video"];


        $rutaImagen = __DIR__ . "/../uploads/thumbnails/" . $video["miniatura"];



        if(file_exists($rutaVideo))
        {

            unlink($rutaVideo);

        }



        if(file_exists($rutaImagen))
        {

            unlink($rutaImagen);

        }


    }



    $sql = "
    DELETE FROM videos
    WHERE id_video = :id
    ";



    $stmt = $this->conexion->prepare($sql);



    $stmt->bindParam(
        ":id",
        $id
    );



    return $stmt->execute();


}
// VIDEOS PUBLICADOS

public function listarPublicos()
{


    $sql = "

    SELECT

    videos.*,

    categorias.nombre AS categoria


    FROM videos


    INNER JOIN categorias

    ON videos.id_categoria = categorias.id_categoria


    WHERE videos.estado='publicado'


    ORDER BY fecha_publicacion DESC

    ";



    $stmt = $this->conexion->prepare($sql);


    $stmt->execute();



    return $stmt->fetchAll(PDO::FETCH_ASSOC);


}

// CAPITULOS DE TEMPORADA

public function capitulosTemporada($idTemporada)
{


    $sql="

    SELECT *

    FROM videos

    WHERE id_temporada=:temporada

    AND tipo_contenido='serie'

    AND estado='publicado'


    ORDER BY numero_capitulo ASC


    ";



    $stmt=$this->conexion->prepare($sql);



    $stmt->execute([

        ":temporada"=>$idTemporada

    ]);



    return $stmt->fetchAll(PDO::FETCH_ASSOC);


}

// CAPITULOS DE LA MISMA TEMPORADA

public function capitulosRelacionados($idTemporada)
{


    $sql="

    SELECT *

    FROM videos


    WHERE id_temporada=:temporada

    AND tipo_contenido='serie'

    AND estado='publicado'


    ORDER BY numero_capitulo ASC


    ";



    $stmt=$this->conexion->prepare($sql);



    $stmt->execute([

        ":temporada"=>$idTemporada

    ]);



    return $stmt->fetchAll(PDO::FETCH_ASSOC);


}
// PRIMER CAPITULO DE TEMPORADA

public function primerCapituloTemporada($idTemporada)
{


    $sql="

    SELECT *

    FROM videos

    WHERE id_temporada=:temporada

    AND tipo_contenido='serie'

    AND estado='publicado'


    ORDER BY numero_capitulo ASC

    LIMIT 1


    ";



    $stmt=$this->conexion->prepare($sql);



    $stmt->execute([

        ":temporada"=>$idTemporada

    ]);



    return $stmt->fetch(PDO::FETCH_ASSOC);


}

// ULTIMO CAPITULO DE UNA TEMPORADA

public function ultimoCapituloTemporada($idTemporada)
{


    $sql = "

    SELECT *

    FROM videos

    WHERE id_temporada=:temporada

    AND tipo_contenido='serie'

    AND estado='publicado'

    ORDER BY numero_capitulo DESC

    LIMIT 1

    ";


    $stmt=$this->conexion->prepare($sql);


    $stmt->execute([

        ":temporada"=>$idTemporada

    ]);


    return $stmt->fetch(PDO::FETCH_ASSOC);


}


// =========================================
// SIGUIENTE VIDEO INDEPENDIENTE
// =========================================

public function siguienteVideoIndependiente(
    $idCategoria,
    $idActual
)
{
    // Recorre la categoria hacia adelante segun el orden de carga.
    // Al llegar al ultimo video no vuelve al primero, evitando bucles.
    $sql = "
        SELECT *
        FROM videos
        WHERE id_categoria = :categoria
          AND tipo_contenido = 'video'
          AND estado = 'publicado'
          AND id_video > :actual
        ORDER BY id_video ASC
        LIMIT 1
    ";

    $stmt = $this->conexion->prepare($sql);
    $stmt->execute([
        ':categoria' => $idCategoria,
        ':actual' => $idActual,
    ]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}
}