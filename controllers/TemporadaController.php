<?php


require_once __DIR__ . "/../models/Temporada.php";


class TemporadaController
{


    private $modelo;



    public function __construct()
    {

        $this->modelo = new Temporada();

    }




    public function listar()
    {

        return $this->modelo->listar();

    }




    public function listarPorSerie($idSerie)
    {

        return $this->modelo->listarPorSerie($idSerie);

    }




    public function buscar($id)
    {

        return $this->modelo->buscar($id);

    }




    public function crear($datos)
    {

        return $this->modelo->crear($datos);

    }




    public function actualizar($datos)
    {

        return $this->modelo->actualizar($datos);

    }




    public function eliminar($id)
    {

        return $this->modelo->eliminar($id);

    }

    

    public function listarPublicas($idSerie)
{

    return $this->modelo->listarPublicas($idSerie);

}

// TEMPORADA SIGUIENTE

public function siguienteTemporada($idSerie,$numeroTemporada)
{

    return $this->modelo->siguienteTemporada(
        $idSerie,
        $numeroTemporada
    );

}





// TEMPORADA ANTERIOR

public function anteriorTemporada($idSerie,$numeroTemporada)
{

    return $this->modelo->anteriorTemporada(
        $idSerie,
        $numeroTemporada
    );

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

    return $this->modelo->listarFiltrado(
        $buscar,
        $serie,
        $orden
    );

}


// =========================================
// SERIES PARA FILTRO
// =========================================

public function seriesFiltro()
{

    return $this->modelo->seriesFiltro();

}

}