<?php

require_once __DIR__ . '/../models/KnowledgeBase.php';

class KnowledgeController
{
    private KnowledgeBase $model;

    public function __construct()
    {
        $this->model = new KnowledgeBase();
    }

    public function tablasDisponibles(): bool
    {
        return $this->model->tablasDisponibles();
    }

    public function resumen(): array
    {
        return $this->model->resumen();
    }

    public function listarDocumentos(string $buscar = '', string $estado = ''): array
    {
        return $this->model->listarDocumentos($buscar, $estado);
    }

    public function cursosPublicados(): array
    {
        return $this->model->cursosPublicados();
    }

    public function videosIndexados(): array
    {
        return $this->model->videosIndexados();
    }
}
