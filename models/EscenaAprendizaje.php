<?php
/**
 * DEVIOZ V4.4.4 - Reflexiones y escenas repasadas por usuario.
 * Esta actividad NO afecta notas, cuestionarios ni progreso de Learning Lab.
 */
require_once __DIR__ . '/../config/conexion.php';

final class EscenaAprendizaje
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? (new Conexion())->conectar();
    }

    public function instalado(): bool
    {
        try {
            return (bool)$this->pdo->query("SHOW TABLES LIKE 'escenas_aprendizaje_usuario'")->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    /** @return array<int,array{completada:bool,nota:string}> */
    public function estadoVideo(int $idUsuario, int $idVideo): array
    {
        if ($idUsuario <= 0 || $idVideo <= 0) return [];
        $stmt = $this->pdo->prepare("
            SELECT c.id_capitulo, e.completada, e.nota
            FROM ai_capitulos_generaciones g
            INNER JOIN ai_capitulos_video c ON c.id_generacion = g.id_generacion
            LEFT JOIN escenas_aprendizaje_usuario e
              ON e.id_capitulo = c.id_capitulo AND e.id_usuario = :usuario
            WHERE g.id_video = :video AND g.estado = 'publicada'
            ORDER BY c.orden ASC, c.id_capitulo ASC
        ");
        $stmt->execute([':usuario' => $idUsuario, ':video' => $idVideo]);
        $resultado = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $resultado[(int)$row['id_capitulo']] = [
                'completada' => (bool)($row['completada'] ?? false),
                'nota' => (string)($row['nota'] ?? ''),
            ];
        }
        return $resultado;
    }

    /**
     * Guarda una reflexión del usuario para una escena de la versión publicada.
     * La marca "repasada" es una autoevaluación; nunca equivale a una nota.
     * @return array{completada:bool,nota:string}
     */
    public function guardar(int $idUsuario, int $idVideo, int $idCapitulo, string $nota, bool $marcarRepasada): array
    {
        if ($idUsuario <= 0 || $idVideo <= 0 || $idCapitulo <= 0) {
            throw new InvalidArgumentException('La escena seleccionada no es válida.');
        }
        $nota = trim($nota);
        $largo = function_exists('mb_strlen') ? mb_strlen($nota, 'UTF-8') : preg_match_all('/./us', $nota);
        if ($largo === false) {
            throw new InvalidArgumentException('El texto debe estar codificado en UTF-8.');
        }
        if ($largo > 1200) {
            throw new InvalidArgumentException('La reflexión puede tener como máximo 1200 caracteres.');
        }
        if ($marcarRepasada && $largo < 12) {
            throw new InvalidArgumentException('Escribe al menos 12 caracteres sobre lo que aprendiste.');
        }

        $valida = $this->pdo->prepare("
            SELECT c.id_capitulo
            FROM ai_capitulos_video c
            INNER JOIN ai_capitulos_generaciones g ON g.id_generacion = c.id_generacion
            INNER JOIN videos v ON v.id_video = g.id_video
            WHERE c.id_capitulo = :capitulo AND g.id_video = :video
              AND g.estado = 'publicada' AND v.estado = 'publicado'
            LIMIT 1
        ");
        $valida->execute([':capitulo' => $idCapitulo, ':video' => $idVideo]);
        if (!$valida->fetchColumn()) {
            throw new InvalidArgumentException('Esta escena ya no está disponible en la versión publicada del video.');
        }

        // MySQL/MariaDB: la clave UNIQUE evita que el mismo usuario duplique su progreso.
        // Guardar un nuevo apunte nunca desmarca una escena que ya se había repasado.
        $stmt = $this->pdo->prepare("
            INSERT INTO escenas_aprendizaje_usuario
                (id_usuario, id_capitulo, nota, completada, fecha_completado)
            VALUES (:usuario, :capitulo, :nota, :completada, :fecha)
            ON DUPLICATE KEY UPDATE
                nota = VALUES(nota),
                fecha_completado = CASE
                    WHEN VALUES(completada) = 1 AND fecha_completado IS NULL THEN NOW()
                    ELSE fecha_completado
                END,
                completada = GREATEST(completada, VALUES(completada)),
                fecha_actualizacion = NOW()
        ");
        $stmt->execute([
            ':usuario' => $idUsuario,
            ':capitulo' => $idCapitulo,
            ':nota' => $nota,
            ':completada' => $marcarRepasada ? 1 : 0,
            ':fecha' => $marcarRepasada ? date('Y-m-d H:i:s') : null,
        ]);

        $leer = $this->pdo->prepare('SELECT nota, completada FROM escenas_aprendizaje_usuario WHERE id_usuario = :usuario AND id_capitulo = :capitulo');
        $leer->execute([':usuario' => $idUsuario, ':capitulo' => $idCapitulo]);
        $row = $leer->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'completada' => (bool)($row['completada'] ?? false),
            'nota' => (string)($row['nota'] ?? ''),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function resumenUsuario(int $idUsuario, int $limite = 60): array
    {
        if ($idUsuario <= 0) return [];
        $limite = max(1, min(100, $limite));
        $stmt = $this->pdo->prepare("
            SELECT v.id_video, v.titulo, COUNT(*) AS total,
                SUM(CASE WHEN e.completada = 1 THEN 1 ELSE 0 END) AS repasadas,
                MAX(e.fecha_actualizacion) AS ultima_actividad,
                SUM(CASE WHEN e.id_estudio IS NOT NULL THEN 1 ELSE 0 END) AS escenas_con_actividad
            FROM ai_capitulos_generaciones g
            INNER JOIN videos v ON v.id_video = g.id_video AND v.estado = 'publicado'
            INNER JOIN ai_capitulos_video c ON c.id_generacion = g.id_generacion
            LEFT JOIN escenas_aprendizaje_usuario e
              ON e.id_capitulo = c.id_capitulo AND e.id_usuario = :usuario
            WHERE g.estado = 'publicada'
            GROUP BY v.id_video, v.titulo, g.id_generacion
            HAVING escenas_con_actividad > 0
            ORDER BY ultima_actividad DESC
            LIMIT {$limite}
        ");
        $stmt->execute([':usuario' => $idUsuario]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
