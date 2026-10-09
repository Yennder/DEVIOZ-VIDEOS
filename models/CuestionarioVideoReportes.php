<?php

require_once __DIR__ . '/../config/conexion.php';

/** Consultas de seguimiento V4.5.4.1: no modifica los intentos ni las evaluaciones Learning. */
class CuestionarioVideoReportes
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? (new Conexion())->conectar();
    }

    public function instalado(): bool
    {
        try {
            return (bool)$this->db->query("SHOW TABLES LIKE 'video_cuestionario_intentos'")->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    private function consultar(string $sql, array $parametros = [], ?int $limite = null, int $offset = 0): array
    {
        $stmt = $this->db->prepare($sql);
        $posicion = 1;
        foreach ($parametros as $valor) {
            $stmt->bindValue($posicion++, $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        if ($limite !== null) {
            $stmt->bindValue($posicion++, max(1, min(100, $limite)), PDO::PARAM_INT);
            $stmt->bindValue($posicion++, max(0, $offset), PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function contar(string $sql, array $parametros): int
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($parametros);
        return (int)$stmt->fetchColumn();
    }

    public function contarRespondidos(int $idUsuario): int
    {
        return $this->contar(
            "SELECT COUNT(DISTINCT c.id_video) FROM video_cuestionario_intentos i
             JOIN video_cuestionarios c ON c.id_cuestionario = i.id_cuestionario
             JOIN videos v ON v.id_video = c.id_video
             WHERE i.id_usuario = ? AND v.estado = 'publicado'",
            [$idUsuario]
        );
    }

    public function contarPendientes(int $idUsuario): int
    {
        return $this->contar(
            "SELECT COUNT(*) FROM video_cuestionarios c
             JOIN videos v ON v.id_video = c.id_video
             WHERE c.estado = 'publicado' AND v.estado = 'publicado'
               AND NOT EXISTS (
                 SELECT 1 FROM video_cuestionario_intentos i
                 JOIN video_cuestionarios anterior ON anterior.id_cuestionario = i.id_cuestionario
                 WHERE anterior.id_video = c.id_video AND i.id_usuario = ?
               )",
            [$idUsuario]
        );
    }

    /** Incluye versiones archivadas: el historial de aprendizaje nunca desaparece al regenerar preguntas. */
    public function respondidos(int $idUsuario, int $limite, int $offset): array
    {
        return $this->consultar(
            "SELECT r.id_video, r.titulo, r.intentos, r.mejor_puntaje,
                    r.ultimo_id, r.ultima_fecha, ultimo.puntaje AS ultima_nota,
                    activo.id_cuestionario AS version_publicada
             FROM (
                 SELECT c.id_video, v.titulo, COUNT(*) AS intentos,
                        MAX(i.puntaje) AS mejor_puntaje,
                        MAX(i.id_intento) AS ultimo_id,
                        MAX(i.fecha_realizacion) AS ultima_fecha
                 FROM video_cuestionario_intentos i
                 JOIN video_cuestionarios c ON c.id_cuestionario = i.id_cuestionario
                 JOIN videos v ON v.id_video = c.id_video
                 WHERE i.id_usuario = ? AND v.estado = 'publicado'
                 GROUP BY c.id_video, v.titulo
             ) r
             JOIN video_cuestionario_intentos ultimo ON ultimo.id_intento = r.ultimo_id
             LEFT JOIN video_cuestionarios activo ON activo.id_video = r.id_video AND activo.estado = 'publicado'
             ORDER BY r.ultimo_id DESC LIMIT ? OFFSET ?",
            [$idUsuario], $limite, $offset
        );
    }

    public function pendientes(int $idUsuario, int $limite, int $offset): array
    {
        return $this->consultar(
            "SELECT v.id_video, v.titulo, c.fecha_publicacion
             FROM video_cuestionarios c
             JOIN videos v ON v.id_video = c.id_video
             WHERE c.estado = 'publicado' AND v.estado = 'publicado'
               AND NOT EXISTS (
                 SELECT 1 FROM video_cuestionario_intentos i
                 JOIN video_cuestionarios anterior ON anterior.id_cuestionario = i.id_cuestionario
                 WHERE anterior.id_video = c.id_video AND i.id_usuario = ?
               )
             ORDER BY c.fecha_publicacion DESC, c.id_cuestionario DESC LIMIT ? OFFSET ?",
            [$idUsuario], $limite, $offset
        );
    }

    private function condicionAdmin(int $idVideo, int $idUsuario): array
    {
        $condiciones = [];
        $params = [];
        if ($idVideo > 0) {
            $condiciones[] = 'c.id_video = ?';
            $params[] = $idVideo;
        }
        if ($idUsuario > 0) {
            $condiciones[] = 'i.id_usuario = ?';
            $params[] = $idUsuario;
        }
        return [$condiciones ? ' WHERE ' . implode(' AND ', $condiciones) : '', $params];
    }

    public function opcionesAdmin(): array
    {
        // Mostrar tambien videos sin intentos: un enlace 'Notas' desde la lista
        // de videos debe conservar el filtro aunque todavia no existan respuestas.
        $videos = $this->db->query(
            'SELECT id_video, titulo FROM videos ORDER BY titulo, id_video'
        )->fetchAll(PDO::FETCH_ASSOC);
        $usuarios = $this->db->query(
            'SELECT id_usuario, nombre FROM usuarios ORDER BY nombre, id_usuario'
        )->fetchAll(PDO::FETCH_ASSOC);
        return ['videos' => $videos, 'usuarios' => $usuarios];
    }

    public function estadisticasAdmin(int $idVideo = 0, int $idUsuario = 0): array
    {
        [$donde, $params] = $this->condicionAdmin($idVideo, $idUsuario);
        $filas = $this->consultar(
            'SELECT COUNT(*) AS total_intentos, COUNT(DISTINCT i.id_usuario) AS participantes,
                    COUNT(DISTINCT c.id_video) AS videos_evaluados
             FROM video_cuestionario_intentos i
             JOIN video_cuestionarios c ON c.id_cuestionario = i.id_cuestionario' . $donde,
            $params
        );
        return $filas[0] ?? ['total_intentos' => 0, 'participantes' => 0, 'videos_evaluados' => 0];
    }

    public function contarResultadosAdmin(int $idVideo = 0, int $idUsuario = 0): int
    {
        [$donde, $params] = $this->condicionAdmin($idVideo, $idUsuario);
        return $this->contar(
            'SELECT COUNT(*) FROM (
                SELECT i.id_usuario, c.id_video FROM video_cuestionario_intentos i
                JOIN video_cuestionarios c ON c.id_cuestionario = i.id_cuestionario' . $donde . '
                GROUP BY i.id_usuario, c.id_video
             ) resultados',
            $params
        );
    }

    public function resultadosAdmin(int $idVideo, int $idUsuario, int $limite, int $offset): array
    {
        [$donde, $params] = $this->condicionAdmin($idVideo, $idUsuario);
        return $this->consultar(
            'SELECT r.*, ultimo.puntaje AS ultima_nota, ultimo.aciertos AS ultimos_aciertos
             FROM (
                 SELECT i.id_usuario, u.nombre, c.id_video, v.titulo,
                        COUNT(*) AS intentos, MAX(i.puntaje) AS mejor_nota,
                        MAX(i.id_intento) AS ultimo_id, MAX(i.fecha_realizacion) AS ultima_fecha
                 FROM video_cuestionario_intentos i
                 JOIN video_cuestionarios c ON c.id_cuestionario = i.id_cuestionario
                 JOIN usuarios u ON u.id_usuario = i.id_usuario
                 JOIN videos v ON v.id_video = c.id_video' . $donde . '
                 GROUP BY i.id_usuario, u.nombre, c.id_video, v.titulo
             ) r
             JOIN video_cuestionario_intentos ultimo ON ultimo.id_intento = r.ultimo_id
             ORDER BY r.ultimo_id DESC LIMIT ? OFFSET ?',
            $params, $limite, $offset
        );
    }

    /** Solo para un par usuario-video ya validado por la pagina administrativa. */
    public function intentosAdmin(int $idVideo, int $idUsuario): array
    {
        if ($idVideo <= 0 || $idUsuario <= 0) {
            return [];
        }
        return $this->consultar(
            'SELECT i.id_intento, i.numero_intento, i.aciertos, i.puntaje,
                    i.fecha_realizacion, c.id_cuestionario, c.estado AS estado_version
             FROM video_cuestionario_intentos i
             JOIN video_cuestionarios c ON c.id_cuestionario = i.id_cuestionario
             WHERE c.id_video = ? AND i.id_usuario = ?
             ORDER BY i.id_intento DESC LIMIT ? OFFSET ?',
            [$idVideo, $idUsuario], 100, 0
        );
    }
}
