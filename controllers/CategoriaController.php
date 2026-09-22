<?php

require_once __DIR__ . "/../models/Categoria.php";


class CategoriaController
{

    private $modelo;


    public function __construct()
    {

        $this->modelo = new Categoria();

    }


    // LISTAR CATEGORIAS

    public function listar()
    {

        return $this->modelo->listar();

    }


    // LISTAR CATEGORIAS CON FILTROS

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


    // CREAR CATEGORIA

    public function crear($nombre,$descripcion)
    {

        return $this->modelo->crear(
            $nombre,
            $descripcion
        );

    }


    // BUSCAR CATEGORIA

    public function buscar($id)
    {

        return $this->modelo->buscar($id);

    }


    // ACTUALIZAR CATEGORIA

    public function actualizar($id,$nombre,$descripcion)
    {

        return $this->modelo->actualizar(
            $id,
            $nombre,
            $descripcion
        );

    }


    // ELIMINAR CATEGORIA

    public function eliminar($id)
    {

        return $this->modelo->eliminar($id);

    }

}