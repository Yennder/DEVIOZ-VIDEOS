<?php

require_once __DIR__ . '/../config/conexion.php';

class ResumenIA
{
    private PDO $conexion;

    public function __construct()
    {
        $db = new Conexion();
        $this->conexion = $db->conectar();
    }

    public function tablaDisponible(): bool
    {
        try {
            $stmt = $this->conexion->query("SHOW TABLES LIKE 'ai_resumenes'");
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    public function obtener(string $tipo, int $id): ?array
    {
        if (!$this->tablaDisponible()) {
            return null;
        }

        $campo = $tipo === 'curso' ? 'id_curso' : 'id_video';
        $stmt = $this->conexion->prepare("SELECT * FROM ai_resumenes WHERE tipo = :tipo AND {$campo} = :id LIMIT 1");
        $stmt->execute([':tipo' => $tipo, ':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        foreach (['puntos_clave', 'conceptos', 'tecnologias'] as $campoJson) {
            $valor = json_decode((string)($row[$campoJson] ?? '[]'), true);
            $row[$campoJson] = is_array($valor) ? array_values($valor) : [];
        }

        return $row;
    }

    public function guardar(string $tipo, int $id, array $contenido, string $fuenteHash, int $fuenteItems, ?string $proveedor, ?string $modelo, int $idUsuario): array
    {
        if (!$this->tablaDisponible()) {
            throw new RuntimeException('Primero importa database/migracion_v4_4_1_resumenes_ia.sql.');
        }

        $idVideo = $tipo === 'video' ? $id : null;
        $idCurso = $tipo === 'curso' ? $id : null;

        $sql = "
            INSERT INTO ai_resumenes
                (tipo, id_video, id_curso, resumen, puntos_clave, conceptos, tecnologias,
                 fuente_hash, fuente_items, proveedor, modelo, generado_por, fecha_generacion)
            VALUES
                (:tipo, :video, :curso, :resumen, :puntos, :conceptos, :tecnologias,
                 :hash, :items, :proveedor, :modelo, :usuario, NOW())
            ON DUPLICATE KEY UPDATE
                resumen = VALUES(resumen),
                puntos_clave = VALUES(puntos_clave),
                conceptos = VALUES(conceptos),
                tecnologias = VALUES(tecnologias),
                fuente_hash = VALUES(fuente_hash),
                fuente_items = VALUES(fuente_items),
                proveedor = VALUES(proveedor),
                modelo = VALUES(modelo),
                generado_por = VALUES(generado_por),
                fecha_generacion = NOW()
        ";

        $stmt = $this->conexion->prepare($sql);
        $stmt->execute([
            ':tipo' => $tipo,
            ':video' => $idVideo,
            ':curso' => $idCurso,
            ':resumen' => (string)$contenido['resumen'],
            ':puntos' => json_encode($contenido['puntos_clave'] ?? [], JSON_UNESCAPED_UNICODE),
            ':conceptos' => json_encode($contenido['conceptos'] ?? [], JSON_UNESCAPED_UNICODE),
            ':tecnologias' => json_encode($contenido['tecnologias'] ?? [], JSON_UNESCAPED_UNICODE),
            ':hash' => $fuenteHash,
            ':items' => $fuenteItems,
            ':proveedor' => $proveedor,
            ':modelo' => $modelo,
            ':usuario' => $idUsuario > 0 ? $idUsuario : null,
        ]);

        return $this->obtener($tipo, $id) ?? [];
    }

    public function video(int $idVideo): ?array
    {
        $stmt = $this->conexion->prepare("
            SELECT
                v.id_video,
                v.titulo,
                v.descripcion,
                c.nombre AS categoria,
                s.titulo AS serie,
                t.numero_temporada,
                v.numero_capitulo,
                vt.id_transcripcion,
                vt.estado AS transcripcion_estado,
                vt.fecha_actualizacion AS transcripcion_actualizacion,
                vt.duracion_segundos,
                vt.texto_completo
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
            SELECT seg.orden, seg.inicio_segundos, seg.fin_segundos, seg.texto
            FROM video_transcripcion_segmentos seg
            INNER JOIN video_transcripciones vt ON vt.id_transcripcion = seg.id_transcripcion
            WHERE vt.id_video = :video AND vt.estado = 'completada'
            ORDER BY seg.orden ASC, seg.inicio_segundos ASC
        ");
        $stmt->execute([':video' => $idVideo]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function curso(int $idCurso): ?array
    {
        $stmt = $this->conexion->prepare("
            SELECT id_curso, titulo, descripcion, nivel, duracion_estimada_minutos, fecha_actualizacion
            FROM learning_cursos
            WHERE id_curso = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $idCurso]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function videosCurso(int $idCurso): array
    {
        $stmt = $this->conexion->prepare("
            SELECT
                l.id_leccion,
                l.orden,
                l.titulo_personalizado,
                v.id_video,
                v.titulo,
                v.descripcion,
                vt.estado AS transcripcion_estado,
                vt.fecha_actualizacion AS transcripcion_actualizacion,
                vt.duracion_segundos
            FROM learning_curso_lecciones l
            INNER JOIN videos v ON v.id_video = l.id_video
            LEFT JOIN video_transcripciones vt ON vt.id_video = v.id_video
            WHERE l.id_curso = :curso
            ORDER BY l.orden ASC, l.id_leccion ASC
        ");
        $stmt->execute([':curso' => $idCurso]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
