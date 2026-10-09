<?php

require_once __DIR__ . '/../models/Valoracion.php';

class ValoracionController
{
    private Valoracion $modelo;

    public function __construct(?Valoracion $modelo = null)
    {
        $this->modelo = $modelo ?? new Valoracion();
    }

    public function resumenVideo(int $idVideo, int $idUsuario = 0): array
    {
        return $this->modelo->resumenVideo($idVideo, $idUsuario);
    }

    public function guardar(int $idUsuario, int $idVideo, int $estrellas): array
    {
        return $this->modelo->guardar($idUsuario, $idVideo, $estrellas);
    }
}
