<?php

require_once __DIR__ . '/../services/ResumenIAService.php';

class ResumenIAController
{
    private ResumenIAService $service;

    public function __construct()
    {
        $this->service = new ResumenIAService();
    }

    public function estado(string $tipo, int $id): array
    {
        return $this->service->estado($tipo, $id);
    }

    public function generar(string $tipo, int $id, int $idUsuario, bool $forzar = false): array
    {
        return $this->service->generar($tipo, $id, $idUsuario, $forzar);
    }
}
