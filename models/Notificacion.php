<?php

require_once __DIR__ . '/../config/conexion.php';

class Notificacion
{
    private PDO $conexion;
    private ?bool $tablaDisponible = null;

    public function __construct(?PDO $conexion = null)
    {
        if ($conexion instanceof PDO) {
            $this->conexion = $conexion;
            return;
        }
        $db = new Conexion();
        $this->conexion = $db->conectar();
    }

    public function tablaDisponible(): bool
    {
        if ($this->tablaDisponible !== null) {
            return $this->tablaDisponible;
        }

        try {
            $stmt = $this->conexion->query("SELECT 1 FROM notificaciones LIMIT 1");
            $this->tablaDisponible = $stmt !== false;
        } catch (Throwable $e) {
            $this->tablaDisponible = false;
        }

        return $this->tablaDisponible;
    }

    public function crear(
        int $idUsuario,
        string $tipo,
        string $titulo,
        string $mensaje,
        ?string $url = null,
        string $icono = '🔔',
        ?string $claveUnica = null
    ): bool {
        if ($idUsuario <= 0 || !$this->tablaDisponible()) {
            return false;
        }

        $tipo = mb_substr(trim($tipo), 0, 50);
        $titulo = mb_substr(trim($titulo), 0, 180);
        $mensaje = mb_substr(trim($mensaje), 0, 500);
        $url = $url !== null ? mb_substr(trim($url), 0, 255) : null;
        $icono = mb_substr(trim($icono), 0, 20);
        $claveUnica = $claveUnica !== null ? mb_substr(trim($claveUnica), 0, 191) : null;

        if ($tipo === '' || $titulo === '' || $mensaje === '') {
            return false;
        }

        $sql = "INSERT IGNORE INTO notificaciones
                (id_usuario,tipo,titulo,mensaje,url,icono,clave_unica)
                VALUES (:usuario,:tipo,:titulo,:mensaje,:url,:icono,:clave)";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute([
            ':usuario' => $idUsuario,
            ':tipo' => $tipo,
            ':titulo' => $titulo,
            ':mensaje' => $mensaje,
            ':url' => $url ?: null,
            ':icono' => $icono ?: '🔔',
            ':clave' => $claveUnica ?: null,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function listar(int $idUsuario, int $limite = 30): array
    {
        if ($idUsuario <= 0 || !$this->tablaDisponible()) {
            return [];
        }
        $limite = max(1, min(100, $limite));
        $stmt = $this->conexion->prepare("SELECT * FROM notificaciones WHERE id_usuario=:usuario ORDER BY leida ASC, fecha_creacion DESC LIMIT {$limite}");
        $stmt->execute([':usuario' => $idUsuario]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function noLeidas(int $idUsuario, int $limite = 6): array
    {
        if ($idUsuario <= 0 || !$this->tablaDisponible()) {
            return [];
        }
        $limite = max(1, min(20, $limite));
        $stmt = $this->conexion->prepare("SELECT * FROM notificaciones WHERE id_usuario=:usuario AND leida=0 ORDER BY fecha_creacion DESC LIMIT {$limite}");
        $stmt->execute([':usuario' => $idUsuario]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contarNoLeidas(int $idUsuario): int
    {
        if ($idUsuario <= 0 || !$this->tablaDisponible()) {
            return 0;
        }
        $stmt = $this->conexion->prepare("SELECT COUNT(*) FROM notificaciones WHERE id_usuario=:usuario AND leida=0");
        $stmt->execute([':usuario' => $idUsuario]);
        return (int)$stmt->fetchColumn();
    }

    public function marcarLeida(int $idNotificacion, int $idUsuario): bool
    {
        if ($idNotificacion <= 0 || $idUsuario <= 0 || !$this->tablaDisponible()) {
            return false;
        }
        $stmt = $this->conexion->prepare("UPDATE notificaciones SET leida=1, fecha_leida=COALESCE(fecha_leida,NOW()) WHERE id_notificacion=:id AND id_usuario=:usuario");
        $stmt->execute([':id'=>$idNotificacion, ':usuario'=>$idUsuario]);
        return $stmt->rowCount() > 0;
    }

    public function marcarTodas(int $idUsuario): int
    {
        if ($idUsuario <= 0 || !$this->tablaDisponible()) {
            return 0;
        }
        $stmt = $this->conexion->prepare("UPDATE notificaciones SET leida=1, fecha_leida=COALESCE(fecha_leida,NOW()) WHERE id_usuario=:usuario AND leida=0");
        $stmt->execute([':usuario'=>$idUsuario]);
        return $stmt->rowCount();
    }

    public function eliminar(int $idNotificacion, int $idUsuario): bool
    {
        if ($idNotificacion <= 0 || $idUsuario <= 0 || !$this->tablaDisponible()) {
            return false;
        }
        $stmt = $this->conexion->prepare("DELETE FROM notificaciones WHERE id_notificacion=:id AND id_usuario=:usuario");
        $stmt->execute([':id'=>$idNotificacion, ':usuario'=>$idUsuario]);
        return $stmt->rowCount() > 0;
    }

    public function sincronizarRecordatorios(int $idUsuario): void
    {
        if ($idUsuario <= 0 || !$this->tablaDisponible()) {
            return;
        }

        // Recordatorios de capacitaciones que vencen dentro de los próximos 3 días.
        $sql = "SELECT a.id_asignacion,cap.nombre,cap.fecha_limite
                FROM learning_asignaciones a
                INNER JOIN learning_capacitaciones cap ON cap.id_capacitacion=a.id_capacitacion
                WHERE a.id_usuario=:usuario
                  AND a.estado IN ('pendiente','en_progreso')
                  AND cap.estado IN ('planificada','activa')
                  AND cap.fecha_limite BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 3 DAY)";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute([':usuario'=>$idUsuario]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $dias = (int)((strtotime($r['fecha_limite']) - strtotime(date('Y-m-d'))) / 86400);
            $cuando = $dias <= 0 ? 'vence hoy' : ($dias === 1 ? 'vence mañana' : "vence en {$dias} días");
            $this->crear(
                $idUsuario,
                'capacitacion_vencimiento',
                'Capacitación próxima a vencer',
                $r['nombre'] . ' ' . $cuando . '.',
                '/DEVIOZ-VIDEOS/public/curso.php?id_asignacion=' . (int)$r['id_asignacion'],
                '⏰',
                'cap_vencimiento:' . (int)$r['id_asignacion'] . ':' . $r['fecha_limite']
            );
        }

        // Si el usuario ya terminó las lecciones y hay evaluación publicada, avisa una sola vez.
        $sql = "SELECT a.id_asignacion,cap.nombre,e.id_evaluacion,e.intentos_permitidos,
                       COUNT(DISTINCT l.id_leccion) AS total,
                       COUNT(DISTINCT CASE WHEN pl.estado='completada' THEN l.id_leccion END) AS completas,
                       COUNT(DISTINCT i.id_intento) AS intentos,
                       MAX(CASE WHEN i.aprobado=1 THEN 1 ELSE 0 END) AS aprobado
                FROM learning_asignaciones a
                INNER JOIN learning_capacitaciones cap ON cap.id_capacitacion=a.id_capacitacion
                INNER JOIN learning_curso_lecciones l ON l.id_curso=cap.id_curso AND l.obligatoria=1
                LEFT JOIN learning_progreso_lecciones pl ON pl.id_asignacion=a.id_asignacion AND pl.id_leccion=l.id_leccion
                INNER JOIN learning_evaluaciones e ON e.id_curso=cap.id_curso AND e.estado='publicada'
                LEFT JOIN learning_intentos i ON i.id_asignacion=a.id_asignacion AND i.id_evaluacion=e.id_evaluacion
                WHERE a.id_usuario=:usuario AND a.estado IN ('pendiente','en_progreso')
                GROUP BY a.id_asignacion,e.id_evaluacion
                HAVING total>0 AND completas>=total AND aprobado=0 AND intentos<e.intentos_permitidos";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute([':usuario'=>$idUsuario]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $this->crear(
                $idUsuario,
                'evaluacion_disponible',
                'Evaluación disponible',
                'Ya completaste las lecciones de ' . $r['nombre'] . '. Puedes rendir la evaluación final.',
                '/DEVIOZ-VIDEOS/public/evaluacion.php?id_asignacion=' . (int)$r['id_asignacion'],
                '📝',
                'eval_disponible:' . (int)$r['id_asignacion'] . ':' . (int)$r['id_evaluacion']
            );
        }
    }
}
