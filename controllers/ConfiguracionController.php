<?php

require_once __DIR__ . "/../models/Configuracion.php";


class ConfiguracionController
{

    private $modelo;


    public function __construct()
    {

        $this->modelo = new Configuracion();

    }


    public function obtener($clave)
    {

        return $this->modelo->obtener($clave);

    }


    public function guardar($clave, $valor)
    {

        return $this->modelo->guardar(
            $clave,
            $valor
        );

    }

}