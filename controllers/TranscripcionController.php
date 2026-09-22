<?php

require_once __DIR__ . '/../models/Transcripcion.php';

class TranscripcionController
{
    private Transcripcion $model;

    public function __construct()
    {
        $this->model = new Transcripcion();
    }

    public function tablasDisponibles(): bool
    {
        return $this->model->tablasDisponibles();
    }

    public function listarVideos(string $buscar = '', string $estado = ''): array
    {
        return $this->model->listarVideos($buscar, $estado);
    }

    public function obtenerPorVideo(int $idVideo): ?array
    {
        return $this->model->obtenerPorVideo($idVideo);
    }

    public function obtenerSegmentos(int $idVideo): array
    {
        return $this->model->obtenerSegmentosPorVideo($idVideo);
    }

    public function encolar(int $idVideo, bool $forzar = false, string $idioma = 'es', string $modelo = 'small'): array
    {
        return $this->model->encolar($idVideo, $forzar, $idioma, $modelo);
    }

    public function guardarCorrecciones(int $idVideo, array $textos): void
    {
        $this->model->actualizarTextosSegmentos($idVideo, $textos);
        $this->escribirVtt($idVideo);
    }

    public function escribirVtt(int $idVideo): string
    {
        $directorio = __DIR__ . '/../uploads/subtitulos';
        if (!is_dir($directorio) && !mkdir($directorio, 0775, true) && !is_dir($directorio)) {
            throw new RuntimeException('No se pudo crear uploads/subtitulos.');
        }

        $archivo = 'video_' . $idVideo . '_es.vtt';
        $ruta = $directorio . '/' . $archivo;
        $contenido = $this->model->generarVttDesdeVideo($idVideo);

        if (file_put_contents($ruta, $contenido, LOCK_EX) === false) {
            throw new RuntimeException('No se pudo guardar el archivo VTT.');
        }

        $this->model->actualizarArchivoVtt($idVideo, $archivo);
        return $archivo;
    }

    public function obtenerContextoRelevante(int $idVideo, string $pregunta): array
    {
        return $this->model->obtenerContextoRelevante($idVideo, $pregunta);
    }
}
