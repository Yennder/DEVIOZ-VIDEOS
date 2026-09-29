<?php

require_once __DIR__ . '/../models/Busqueda.php';

class BusquedaController
{
    private Busqueda $modelo;

    public function __construct()
    {
        $this->modelo = new Busqueda();
    }

    public function videos(string $q, int $limit=12): array { return $this->modelo->videos($q,$limit); }
    public function series(string $q, int $limit=8): array { return $this->modelo->series($q,$limit); }
    public function cursos(string $q, int $limit=8): array { return $this->modelo->cursos($q,$limit); }
    public function sugerencias(string $q, bool $incluyeCursos=true, int $limit=7): array { return $this->modelo->sugerencias($q,$incluyeCursos,$limit); }
    public function cursosParaScope(): array { return $this->modelo->cursosParaScope(); }
    public function seriesParaScope(): array { return $this->modelo->seriesParaScope(); }
    public function videosParaScope(): array { return $this->modelo->videosParaScope(); }
}
