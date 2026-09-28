<?php

require_once __DIR__ . '/../config/conexion.php';

class KnowledgeBase
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
            $stmt = $this->conexion->query("SHOW TABLES LIKE 'rag_documentos'");
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    public function resumen(): array
    {
        if (!$this->tablasDisponibles()) {
            return [
                'transcritos' => 0,
                'indexados' => 0,
                'pendientes' => 0,
                'errores' => 0,
                'chunks' => 0,
            ];
        }

        $transcritos = (int)$this->conexion->query("SELECT COUNT(*) FROM video_transcripciones WHERE estado='completada'")->fetchColumn();

        $indexados = (int)$this->conexion->query("
            SELECT COUNT(*)
            FROM rag_documentos rd
            INNER JOIN video_transcripciones vt ON vt.id_transcripcion = rd.id_transcripcion
            WHERE rd.estado='indexado'
              AND rd.fecha_fuente IS NOT NULL
              AND rd.fecha_fuente >= vt.fecha_actualizacion
        ")->fetchColumn();

        $errores = (int)$this->conexion->query("SELECT COUNT(*) FROM rag_documentos WHERE estado='error'")->fetchColumn();
        $chunks = (int)$this->conexion->query("SELECT COUNT(*) FROM rag_chunks")->fetchColumn();

        return [
            'transcritos' => $transcritos,
            'indexados' => $indexados,
            'pendientes' => max(0, $transcritos - $indexados),
            'errores' => $errores,
            'chunks' => $chunks,
        ];
    }

    public function listarDocumentos(string $buscar = '', string $estado = ''): array
    {
        if (!$this->tablasDisponibles()) {
            return [];
        }

        $sql = "
            SELECT
                v.id_video,
                v.titulo,
                c.nombre AS categoria,
                s.titulo AS serie,
                vt.id_transcripcion,
                vt.fecha_actualizacion AS transcripcion_actualizada,
                (SELECT COUNT(*) FROM video_transcripcion_segmentos vs WHERE vs.id_transcripcion=vt.id_transcripcion) AS segmentos,
                rd.id_documento,
                rd.estado AS rag_estado_db,
                rd.modelo_embedding,
                rd.dimension,
                rd.total_chunks,
                rd.mensaje_error,
                rd.fecha_fuente,
                rd.fecha_indexado,
                CASE
                    WHEN rd.id_documento IS NULL THEN 'pendiente'
                    WHEN rd.estado='error' THEN 'error'
                    WHEN rd.estado='indexando' THEN 'indexando'
                    WHEN rd.estado='indexado' AND rd.fecha_fuente >= vt.fecha_actualizacion THEN 'indexado'
                    ELSE 'pendiente'
                END AS indice_estado
            FROM video_transcripciones vt
            INNER JOIN videos v ON v.id_video=vt.id_video
            INNER JOIN categorias c ON c.id_categoria=v.id_categoria
            LEFT JOIN series s ON s.id_serie=v.id_serie
            LEFT JOIN rag_documentos rd ON rd.id_transcripcion=vt.id_transcripcion
            WHERE vt.estado='completada'
        ";

        $params = [];
        if ($buscar !== '') {
            $sql .= " AND (v.titulo LIKE :buscar OR c.nombre LIKE :buscar_categoria OR s.titulo LIKE :buscar_serie) ";
            $like = '%' . $buscar . '%';
            $params[':buscar'] = $like;
            $params[':buscar_categoria'] = $like;
            $params[':buscar_serie'] = $like;
        }

        if (in_array($estado, ['pendiente', 'indexando', 'indexado', 'error'], true)) {
            $sql .= " HAVING indice_estado = :estado ";
            $params[':estado'] = $estado;
        }

        $sql .= " ORDER BY FIELD(indice_estado,'error','indexando','pendiente','indexado'), vt.fecha_actualizacion DESC, v.id_video DESC ";

        $stmt = $this->conexion->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function cursosPublicados(): array
    {
        try {
            $stmt = $this->conexion->query("SELECT id_curso,titulo FROM learning_cursos WHERE estado='publicado' ORDER BY titulo");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }

    public function videosIndexados(): array
    {
        if (!$this->tablasDisponibles()) {
            return [];
        }
        $stmt = $this->conexion->query("
            SELECT v.id_video,v.titulo
            FROM rag_documentos rd
            INNER JOIN videos v ON v.id_video=rd.id_video
            WHERE rd.estado='indexado'
            ORDER BY v.titulo
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
