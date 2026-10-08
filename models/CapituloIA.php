<?php

require_once __DIR__ . '/../config/conexion.php';

class CapituloIA
{
    private PDO $conexion;

    public function __construct()
    {
        $db = new Conexion();
        $this->conexion = $db->conectar();
    }

    public function tablasDisponibles(): bool
    {
        try {
            $a = (bool)$this->conexion->query("SHOW TABLES LIKE 'ai_capitulos_generaciones'")->fetchColumn();
            $b = (bool)$this->conexion->query("SHOW TABLES LIKE 'ai_capitulos_video'")->fetchColumn();
            return $a && $b;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function video(int $idVideo): ?array
    {
        $stmt = $this->conexion->prepare("
            SELECT
                v.id_video,
                v.titulo,
                v.descripcion,
                v.tipo_contenido,
                c.nombre AS categoria,
                s.titulo AS serie,
                t.numero_temporada,
                v.numero_capitulo,
                vt.id_transcripcion,
                vt.estado AS transcripcion_estado,
                vt.duracion_segundos,
                vt.fecha_actualizacion AS transcripcion_actualizacion
            FROM videos v
            LEFT JOIN categorias c ON c.id_categoria = v.id_categoria
            LEFT JOIN series s ON s.id_serie = v.id_serie
            LEFT JOIN temporadas t ON t.id_temporada = v.id_temporada
            LEFT JOIN video_transcripciones vt ON vt.id_video = v.id_video
            WHERE v.id_video = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $idVideo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function segmentosVideo(int $idVideo): array
    {
        $stmt = $this->conexion->prepare("
            SELECT seg.id_segmento, seg.orden, seg.inicio_segundos, seg.fin_segundos, seg.texto
            FROM video_transcripcion_segmentos seg
            INNER JOIN video_transcripciones vt ON vt.id_transcripcion = seg.id_transcripcion
            WHERE vt.id_video = :video AND vt.estado = 'completada'
            ORDER BY seg.orden ASC, seg.inicio_segundos ASC, seg.id_segmento ASC
        ");
        $stmt->execute([':video' => $idVideo]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerGeneracion(int $idGeneracion): ?array
    {
        if (!$this->tablasDisponibles()) {
            return null;
        }
        $stmt = $this->conexion->prepare("
            SELECT g.*, v.titulo AS video_titulo
            FROM ai_capitulos_generaciones g
            INNER JOIN videos v ON v.id_video = g.id_video
            WHERE g.id_generacion = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $idGeneracion]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function obtenerGeneracionVideo(int $idVideo, string $estado): ?array
    {
        if (!$this->tablasDisponibles()) {
            return null;
        }
        $estado = in_array($estado, ['borrador', 'publicada', 'archivada'], true) ? $estado : 'borrador';
        $stmt = $this->conexion->prepare("
            SELECT *
            FROM ai_capitulos_generaciones
            WHERE id_video = :video AND estado = :estado
            ORDER BY id_generacion DESC
            LIMIT 1
        ");
        $stmt->execute([':video' => $idVideo, ':estado' => $estado]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function capitulosGeneracion(int $idGeneracion): array
    {
        if (!$this->tablasDisponibles()) {
            return [];
        }
        $stmt = $this->conexion->prepare("
            SELECT *
            FROM ai_capitulos_video
            WHERE id_generacion = :generacion
            ORDER BY orden ASC, inicio_segundos ASC, id_capitulo ASC
        ");
        $stmt->execute([':generacion' => $idGeneracion]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $conceptos = json_decode((string)($row['conceptos'] ?? '[]'), true);
            $row['conceptos'] = is_array($conceptos) ? array_values($conceptos) : [];
        }
        unset($row);
        return $rows;
    }

    public function publicacionVideo(int $idVideo): ?array
    {
        $generacion = $this->obtenerGeneracionVideo($idVideo, 'publicada');
        if (!$generacion) {
            return null;
        }
        return [
            'generacion' => $generacion,
            'capitulos' => $this->capitulosGeneracion((int)$generacion['id_generacion']),
        ];
    }

    public function crearBorrador(
        int $idVideo,
        string $fuenteHash,
        int $fuenteSegmentos,
        float $duracion,
        array $capitulos,
        ?string $proveedor,
        ?string $modelo,
        int $idUsuario
    ): array {
        if (!$this->tablasDisponibles()) {
            throw new RuntimeException('Primero importa database/migracion_v4_4_3_capitulos_inteligentes.sql.');
        }

        $this->conexion->beginTransaction();
        try {
            $stmtArchive = $this->conexion->prepare("
                UPDATE ai_capitulos_generaciones
                SET estado = 'archivada'
                WHERE id_video = :video AND estado = 'borrador'
            ");
            $stmtArchive->execute([':video' => $idVideo]);

            $stmt = $this->conexion->prepare("
                INSERT INTO ai_capitulos_generaciones
                    (id_video, fuente_hash, fuente_segmentos, duracion_segundos, estado,
                     proveedor, modelo, generado_por, fecha_generacion)
                VALUES
                    (:video, :hash, :segmentos, :duracion, 'borrador',
                     :proveedor, :modelo, :usuario, NOW())
            ");
            $stmt->execute([
                ':video' => $idVideo,
                ':hash' => $fuenteHash,
                ':segmentos' => max(0, $fuenteSegmentos),
                ':duracion' => max(0, $duracion),
                ':proveedor' => $proveedor,
                ':modelo' => $modelo,
                ':usuario' => $idUsuario > 0 ? $idUsuario : null,
            ]);
            $idGeneracion = (int)$this->conexion->lastInsertId();
            $this->insertarCapitulos($idGeneracion, $capitulos);
            $this->conexion->commit();

            return [
                'generacion' => $this->obtenerGeneracion($idGeneracion),
                'capitulos' => $this->capitulosGeneracion($idGeneracion),
            ];
        } catch (Throwable $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $e;
        }
    }

    public function reemplazarCapitulosBorrador(int $idGeneracion, array $capitulos): array
    {
        $generacion = $this->obtenerGeneracion($idGeneracion);
        if (!$generacion || ($generacion['estado'] ?? '') !== 'borrador') {
            throw new RuntimeException('La propuesta ya no está disponible como borrador.');
        }

        $this->conexion->beginTransaction();
        try {
            $del = $this->conexion->prepare('DELETE FROM ai_capitulos_video WHERE id_generacion = :id');
            $del->execute([':id' => $idGeneracion]);
            $this->insertarCapitulos($idGeneracion, $capitulos);
            $upd = $this->conexion->prepare('UPDATE ai_capitulos_generaciones SET fecha_actualizacion = NOW() WHERE id_generacion = :id');
            $upd->execute([':id' => $idGeneracion]);
            $this->conexion->commit();
            return $this->capitulosGeneracion($idGeneracion);
        } catch (Throwable $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $e;
        }
    }

    public function publicar(int $idGeneracion, int $idUsuario): void
    {
        $this->conexion->beginTransaction();
        try {
            $stmt = $this->conexion->prepare('SELECT * FROM ai_capitulos_generaciones WHERE id_generacion = :id FOR UPDATE');
            $stmt->execute([':id' => $idGeneracion]);
            $generacion = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$generacion || ($generacion['estado'] ?? '') !== 'borrador') {
                throw new RuntimeException('Solo se puede publicar una propuesta que siga en borrador.');
            }

            $count = $this->conexion->prepare('SELECT COUNT(*) FROM ai_capitulos_video WHERE id_generacion = :id');
            $count->execute([':id' => $idGeneracion]);
            if ((int)$count->fetchColumn() <= 0) {
                throw new RuntimeException('El borrador no contiene capítulos.');
            }

            $archive = $this->conexion->prepare("
                UPDATE ai_capitulos_generaciones
                SET estado = 'archivada'
                WHERE id_video = :video AND estado = 'publicada'
            ");
            $archive->execute([':video' => (int)$generacion['id_video']]);

            $publish = $this->conexion->prepare("
                UPDATE ai_capitulos_generaciones
                SET estado = 'publicada', publicado_por = :usuario, fecha_publicacion = NOW(), fecha_actualizacion = NOW()
                WHERE id_generacion = :id
            ");
            $publish->execute([
                ':usuario' => $idUsuario > 0 ? $idUsuario : null,
                ':id' => $idGeneracion,
            ]);

            $this->conexion->commit();
        } catch (Throwable $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $e;
        }
    }

    public function descartarBorrador(int $idGeneracion): void
    {
        $stmt = $this->conexion->prepare("DELETE FROM ai_capitulos_generaciones WHERE id_generacion = :id AND estado = 'borrador'");
        $stmt->execute([':id' => $idGeneracion]);
        if ($stmt->rowCount() <= 0) {
            throw new RuntimeException('El borrador ya no existe o ya fue publicado.');
        }
    }

    private function insertarCapitulos(int $idGeneracion, array $capitulos): void
    {
        $stmt = $this->conexion->prepare("
            INSERT INTO ai_capitulos_video
                (id_generacion, orden, inicio_segundos, fin_segundos, titulo, resumen, conceptos)
            VALUES
                (:generacion, :orden, :inicio, :fin, :titulo, :resumen, :conceptos)
        ");

        foreach (array_values($capitulos) as $index => $capitulo) {
            $stmt->execute([
                ':generacion' => $idGeneracion,
                ':orden' => $index + 1,
                ':inicio' => max(0, (float)($capitulo['inicio_segundos'] ?? 0)),
                ':fin' => max(0, (float)($capitulo['fin_segundos'] ?? 0)),
                ':titulo' => mb_substr(trim((string)($capitulo['titulo'] ?? 'Capítulo')), 0, 180, 'UTF-8'),
                ':resumen' => mb_substr(trim((string)($capitulo['resumen'] ?? '')), 0, 2000, 'UTF-8'),
                ':conceptos' => json_encode(array_values($capitulo['conceptos'] ?? []), JSON_UNESCAPED_UNICODE),
            ]);
        }
    }
}
