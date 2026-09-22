<?php


require_once __DIR__ . "/../models/Serie.php";


class SerieController
{


    private $modelo;



    public function __construct()
    {

        $this->modelo = new Serie();

    }




    // =========================
    // ADMIN - LISTAR
    // =========================

    public function listar()
    {

        return $this->modelo->listar();

    }




    // =========================
    // ADMIN - LISTAR FILTRADO
    // =========================

    public function listarFiltrado(
        $buscar = "",
        $estado = "",
        $orden = "recientes"
    )
    {

        return $this->modelo->listarFiltrado(
            $buscar,
            $estado,
            $orden
        );

    }




    // =========================
    // BUSCAR SERIE
    // =========================

    public function buscar($id)
    {

        return $this->modelo->buscar($id);

    }




    // =========================
    // CREAR SERIE
    // =========================

    public function crear($datos)
    {

        return $this->modelo->crear($datos);

    }




    // =========================
    // ACTUALIZAR SERIE
    // =========================

    public function actualizar($datos)
    {

        return $this->modelo->actualizar($datos);

    }




    // =========================
    // ACTUALIZAR IMAGEN
    // =========================

    public function actualizarImagen($id,$imagen)
    {

        return $this->modelo->actualizarImagen(
            $id,
            $imagen
        );

    }




    // =========================
    // ELIMINAR SERIE
    // =========================

    public function eliminar($id)
    {

        return $this->modelo->eliminar($id);

    }




    // =========================
    // PUBLICO - LISTAR
    // =========================

    public function listarPublicas()
    {

        return $this->modelo->listarPublicas();

    }




    // =========================
    // PUBLICO - DETALLE
    // =========================

    public function detallePublico($id)
    {

        return $this->modelo->detallePublico($id);

    }



}