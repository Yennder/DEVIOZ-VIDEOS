<?php

require_once __DIR__ . '/../config/conexion.php';

/** V4.5.3: calificaciones publicas independientes de likes y Learning Lab. */
class Valoracion
{
    private PDO $conexion;

    public function __construct(?PDO $conexion = null)
    {
        $this->conexion = $conexion ?? (new Conexion())->conectar();
    }

    /** @return array{promedio:float,total:int,mi_valoracion:int} */
    public function resumenVideo(int $idVideo, int $idUsuario = 0): array
    {
        $stmt = $this->conexion->prepare(
            'SELECT COALESCE(ROUND(AVG(estrellas), 1), 0) AS promedio,
                    COUNT(*) AS total,
                    MAX(CASE WHEN id_usuario = :usuario THEN estrellas ELSE NULL END) AS mi_valoracion
               FROM video_valoraciones
              WHERE id_video = :video'
        );
        $stmt->execute([':usuario' => $idUsuario, ':video' => $idVideo]);
        $resumen = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'promedio' => (float)($resumen['promedio'] ?? 0),
            'total' => (int)($resumen['total'] ?? 0),
            'mi_valoracion' => (int)($resumen['mi_valoracion'] ?? 0),
        ];
    }

    /** Una calificacion por usuario y video; la misma fila se actualiza. */
    public function guardar(int $idUsuario, int $idVideo, int $estrellas): array
    {
        if ($idUsuario <= 0 || $idVideo <= 0 || $estrellas < 1 || $estrellas > 5) {
            throw new InvalidArgumentException('Selecciona una calificación válida de 1 a 5 estrellas.');
        }

        $existe = $this->conexion->prepare(
            "SELECT id_video FROM videos WHERE id_video = :video AND estado = 'publicado' LIMIT 1"
        );
        $existe->execute([':video' => $idVideo]);
        if (!$existe->fetchColumn()) {
            throw new InvalidArgumentException('El video no está disponible para calificar.');
        }

        $stmt = $this->conexion->prepare(
            'INSERT INTO video_valoraciones (id_usuario, id_video, estrellas)
             VALUES (:usuario, :video, :estrellas)
             ON DUPLICATE KEY UPDATE
                 estrellas = VALUES(estrellas),
                 fecha_actualizacion = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            ':usuario' => $idUsuario,
            ':video' => $idVideo,
            ':estrellas' => $estrellas,
        ]);

        return $this->resumenVideo($idVideo, $idUsuario);
    }
}
