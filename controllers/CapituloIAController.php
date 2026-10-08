<?php

require_once __DIR__ . '/../services/CapituloIAService.php';

class CapituloIAController
{
    private CapituloIAService $service;

    public function __construct()
    {
        $this->service = new CapituloIAService();
    }

    public function tablasDisponibles(): bool
    {
        return $this->service->tablasDisponibles();
    }

    public function publicadosVideo(int $idVideo): array
    {
        return $this->service->publicadosVideo($idVideo);
    }

    public function estadoAdmin(int $idVideo): array
    {
        return $this->service->estadoAdmin($idVideo);
    }

    public function generar(int $idVideo, int $idUsuario): array
    {
        return $this->service->generar($idVideo, $idUsuario);
    }

    public function guardarBorrador(int $idVideo, int $idGeneracion, array $filas): array
    {
        return $this->service->guardarBorrador($idVideo, $idGeneracion, $filas);
    }

    public function publicar(int $idVideo, int $idGeneracion, int $idUsuario): void
    {
        $this->service->publicar($idVideo, $idGeneracion, $idUsuario);
    }

    public function descartar(int $idVideo, int $idGeneracion): void
    {
        $this->service->descartar($idVideo, $idGeneracion);
    }
}
