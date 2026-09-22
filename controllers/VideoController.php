<?php

require_once __DIR__ . "/../models/Video.php";


class VideoController
{


    private $modelo;



    public function __construct()
    {

        $this->modelo = new Video();

    }




    public function listar()
    {

        return $this->modelo->listar();

    }



    public function categorias()
    {

        return $this->modelo->categorias();

    }



    public function crear($datos)
    {

        return $this->modelo->crear($datos);

    }
// BUSCAR VIDEO

public function buscar($id)
{

    return $this->modelo->buscar($id);

}





// ACTUALIZAR VIDEO

public function actualizar($datos)
{

    return $this->modelo->actualizar($datos);

}

// ELIMINAR VIDEO

public function eliminar($id)
{

    return $this->modelo->eliminar($id);

}

public function listarPublicos()
{

    return $this->modelo->listarPublicos();

}

public function buscarPublicos($texto="", $categoria="", $orden="recientes")
{

    return $this->modelo->buscarPublicos(
        $texto,
        $categoria,
        $orden
    );

}
public function listarCategorias()
{

    return $this->modelo->listarCategorias();

}
public function buscarDetalle($id)
{

    return $this->modelo->buscarDetalle($id);

}



public function aumentarVista($id)
{

    return $this->modelo->aumentarVista($id);

}



public function relacionados($categoria,$id)
{

    return $this->modelo->relacionados(
        $categoria,
        $id
    );

}

public function series()
{

    require_once __DIR__ . "/../models/Serie.php";


    $serie = new Serie();


    return $serie->listar();

}



public function temporadas($idSerie)
{

    require_once __DIR__ . "/../models/Temporada.php";


    $temporada = new Temporada();


    return $temporada->listarPorSerie($idSerie);

}

public function capitulosTemporada($idTemporada)
{

    return $this->modelo->capitulosTemporada(
        $idTemporada
    );

}

public function capitulosRelacionados($idTemporada)
{

    return $this->modelo->capitulosRelacionados(
        $idTemporada
    );

}
// PRIMER CAPITULO DE TEMPORADA

public function primerCapituloTemporada($idTemporada)
{


    return $this->modelo->primerCapituloTemporada(
        $idTemporada
    );


}


// ULTIMO CAPITULO TEMPORADA

public function ultimoCapituloTemporada($idTemporada)
{

    return $this->modelo->ultimoCapituloTemporada(
        $idTemporada
    );

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

    return $this->modelo->listarFiltrado(
        $buscar,
        $categoria,
        $tipo,
        $estado,
        $orden
    );

}

// =========================================
// SIGUIENTE VIDEO INDEPENDIENTE
// =========================================

public function siguienteVideoIndependiente(
    $idCategoria,
    $idActual
)
{

    return $this->modelo->siguienteVideoIndependiente(
        $idCategoria,
        $idActual
    );

}


}