<?php


// =========================================
// RUTAS
// =========================================

define(
    "RUTA_VIDEOS",
    __DIR__ . "/../uploads/videos/"
);


define(
    "RUTA_MINIATURAS",
    __DIR__ . "/../uploads/thumbnails/"
);


define(
    "RUTA_SERIES",
    __DIR__ . "/../uploads/series/"
);




// =========================================
// TAMAÑOS MAXIMOS
// =========================================

// 1 GB

define(
    "MAX_VIDEO",
    1024 * 1024 * 1024
);


// 5 MB

define(
    "MAX_IMAGEN",
    5 * 1024 * 1024
);




// =========================================
// FUNCION SUBIR ARCHIVO
// =========================================

function subirArchivo(
    $archivo,
    $tipo
)
{


    // =========================================
    // VALIDAR EXISTENCIA
    // =========================================

    if(
        !isset($archivo)
        ||
        !is_array($archivo)
    )
    {

        return false;

    }




    // =========================================
    // VALIDAR ERROR DE SUBIDA
    // =========================================

    if(
        !isset($archivo["error"])
        ||
        $archivo["error"] !== UPLOAD_ERR_OK
    )
    {

        return false;

    }




    // =========================================
    // VALIDAR ARCHIVO TEMPORAL
    // =========================================

    if(
        empty($archivo["tmp_name"])
        ||
        !is_uploaded_file(
            $archivo["tmp_name"]
        )
    )
    {

        return false;

    }




    // =========================================
    // DATOS DEL ARCHIVO
    // =========================================

    $nombreOriginal =
        $archivo["name"] ?? "";


    $tamano =
        $archivo["size"] ?? 0;


    $extension =
        strtolower(
            pathinfo(
                $nombreOriginal,
                PATHINFO_EXTENSION
            )
        );




    // =========================================
    // DETECTAR MIME REAL
    // =========================================

    $finfo =
        finfo_open(
            FILEINFO_MIME_TYPE
        );


    if(!$finfo)
    {

        return false;

    }


    $mime =
        finfo_file(
            $finfo,
            $archivo["tmp_name"]
        );


    finfo_close(
        $finfo
    );




    // =========================================
    // VIDEO
    // =========================================

    if($tipo === "video")
    {


        $extensionesPermitidas = [

            "mp4",
            "webm"

        ];


        $mimesPermitidos = [

            "video/mp4",
            "video/webm"

        ];


        $ruta =
            RUTA_VIDEOS;




        // EXTENSION

        if(
            !in_array(
                $extension,
                $extensionesPermitidas,
                true
            )
        )
        {

            return false;

        }




        // MIME REAL

        if(
            !in_array(
                $mime,
                $mimesPermitidos,
                true
            )
        )
        {

            return false;

        }




        // TAMAÑO

        if(
            $tamano <= 0
            ||
            $tamano > MAX_VIDEO
        )
        {

            return false;

        }

    }




    // =========================================
    // MINIATURA DE VIDEO
    // =========================================

    elseif($tipo === "imagen")
    {


        $extensionesPermitidas = [

            "jpg",
            "jpeg",
            "png",
            "webp"

        ];


        $mimesPermitidos = [

            "image/jpeg",
            "image/png",
            "image/webp"

        ];


        $ruta =
            RUTA_MINIATURAS;




        // EXTENSION

        if(
            !in_array(
                $extension,
                $extensionesPermitidas,
                true
            )
        )
        {

            return false;

        }




        // MIME REAL

        if(
            !in_array(
                $mime,
                $mimesPermitidos,
                true
            )
        )
        {

            return false;

        }




        // TAMAÑO

        if(
            $tamano <= 0
            ||
            $tamano > MAX_IMAGEN
        )
        {

            return false;

        }

        if(@getimagesize($archivo["tmp_name"]) === false)
        {
            return false;
        }

    }




    // =========================================
    // PORTADA DE SERIE
    // =========================================

    elseif($tipo === "serie")
    {


        $extensionesPermitidas = [

            "jpg",
            "jpeg",
            "png",
            "webp"

        ];


        $mimesPermitidos = [

            "image/jpeg",
            "image/png",
            "image/webp"

        ];


        $ruta =
            RUTA_SERIES;




        // EXTENSION

        if(
            !in_array(
                $extension,
                $extensionesPermitidas,
                true
            )
        )
        {

            return false;

        }




        // MIME REAL

        if(
            !in_array(
                $mime,
                $mimesPermitidos,
                true
            )
        )
        {

            return false;

        }




        // TAMAÑO

        if(
            $tamano <= 0
            ||
            $tamano > MAX_IMAGEN
        )
        {

            return false;

        }

        if(@getimagesize($archivo["tmp_name"]) === false)
        {
            return false;
        }

    }




    // =========================================
    // TIPO INVALIDO
    // =========================================

    else
    {

        return false;

    }




    // =========================================
    // CREAR CARPETA SI NO EXISTE
    // =========================================

    if(!is_dir($ruta))
    {

        if(
            !mkdir(
                $ruta,
                0775,
                true
            )
        )
        {

            return false;

        }

    }




    // =========================================
    // NOMBRE UNICO
    // =========================================

    try
    {

        $nombreSeguro =
            bin2hex(
                random_bytes(16)
            );

    }
    catch(Exception $e)
    {

        $nombreSeguro =
            uniqid(
                "devioz_",
                true
            );

    }


    $nuevoNombre =
        $nombreSeguro
        .
        "."
        .
        $extension;




    // =========================================
    // DESTINO
    // =========================================

    $destino =
        $ruta
        .
        $nuevoNombre;




    // =========================================
    // MOVER ARCHIVO
    // =========================================

    if(
        move_uploaded_file(
            $archivo["tmp_name"],
            $destino
        )
    )
    {

        return $nuevoNombre;

    }




    return false;


}

?>