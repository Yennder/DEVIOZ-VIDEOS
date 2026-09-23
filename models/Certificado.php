<?php

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/Notificacion.php';

class Certificado
{
    private PDO $conexion;
    private Notificacion $notificaciones;
    private ?bool $tablaDisponible = null;

    public function __construct(?PDO $conexion = null)
    {
        if ($conexion instanceof PDO) {
            $this->conexion = $conexion;
        } else {
            $db = new Conexion();
            $this->conexion = $db->conectar();
        }
        $this->notificaciones = new Notificacion($this->conexion);
    }

    public function tablaDisponible(): bool
    {
        if ($this->tablaDisponible !== null) return $this->tablaDisponible;
        try {
            $stmt = $this->conexion->query("SELECT 1 FROM learning_certificados LIMIT 1");
            $this->tablaDisponible = $stmt !== false;
        } catch (Throwable $e) {
            $this->tablaDisponible = false;
        }
        return $this->tablaDisponible;
    }

    private function codigo(int $idAsignacion, string $fecha): string
    {
        $anio = date('Y', strtotime($fecha ?: 'now'));
        return 'DEVIOZ-' . $anio . '-' . str_pad((string)$idAsignacion, 6, '0', STR_PAD_LEFT) . '-' . strtoupper(bin2hex(random_bytes(4)));
    }

    public function emitirPorAsignacion(int $idAsignacion): ?array
    {
        if ($idAsignacion <= 0 || !$this->tablaDisponible()) return null;

        $existente = $this->conexion->prepare("SELECT * FROM learning_certificados WHERE id_asignacion=:id LIMIT 1");
        $existente->execute([':id'=>$idAsignacion]);
        $row = $existente->fetch(PDO::FETCH_ASSOC);
        if ($row) return $row;

        $sql = "SELECT a.id_asignacion,a.id_usuario,a.id_capacitacion,a.fecha_completado,
                       u.nombre AS participante,
                       cap.id_curso,cap.nombre AS capacitacion,
                       c.titulo AS curso,c.duracion_estimada_minutos,
                       COALESCE(MAX(CASE WHEN i.aprobado=1 THEN i.porcentaje END),0) AS nota_final
                FROM learning_asignaciones a
                INNER JOIN usuarios u ON u.id_usuario=a.id_usuario
                INNER JOIN learning_capacitaciones cap ON cap.id_capacitacion=a.id_capacitacion
                INNER JOIN learning_cursos c ON c.id_curso=cap.id_curso
                LEFT JOIN learning_evaluaciones e ON e.id_curso=c.id_curso AND e.estado='publicada'
                LEFT JOIN learning_intentos i ON i.id_asignacion=a.id_asignacion AND i.id_evaluacion=e.id_evaluacion
                WHERE a.id_asignacion=:id AND a.estado='completada'
                GROUP BY a.id_asignacion
                LIMIT 1";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute([':id'=>$idAsignacion]);
        $d = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$d) return null;

        $fechaFinalizacion = $d['fecha_completado'] ?: date('Y-m-d H:i:s');
        $codigo = $this->codigo($idAsignacion, $fechaFinalizacion);
        $ins = $this->conexion->prepare("INSERT INTO learning_certificados
            (id_asignacion,id_usuario,id_curso,id_capacitacion,codigo,nombre_participante,curso_titulo,capacitacion_nombre,duracion_minutos,nota_final,fecha_finalizacion)
            VALUES (:asig,:usuario,:curso,:cap,:codigo,:nombre,:curso_titulo,:capacitacion,:duracion,:nota,:fecha)");
        $ins->execute([
            ':asig'=>$idAsignacion,
            ':usuario'=>(int)$d['id_usuario'],
            ':curso'=>(int)$d['id_curso'],
            ':cap'=>(int)$d['id_capacitacion'],
            ':codigo'=>$codigo,
            ':nombre'=>$d['participante'],
            ':curso_titulo'=>$d['curso'],
            ':capacitacion'=>$d['capacitacion'],
            ':duracion'=>max(0,(int)$d['duracion_estimada_minutos']),
            ':nota'=>max(0,min(100,(float)$d['nota_final'])),
            ':fecha'=>$fechaFinalizacion,
        ]);
        $idCertificado = (int)$this->conexion->lastInsertId();

        $this->notificaciones->crear(
            (int)$d['id_usuario'],
            'certificado_disponible',
            'Certificado disponible',
            'Tu certificado de ' . $d['curso'] . ' ya está disponible para descargar.',
            '/DEVIOZ-VIDEOS/public/certificados.php?codigo=' . rawurlencode($codigo),
            '🎓',
            'certificado:' . $idAsignacion
        );

        return $this->obtenerPorId($idCertificado);
    }

    public function sincronizarUsuario(int $idUsuario): void
    {
        if ($idUsuario <= 0 || !$this->tablaDisponible()) return;
        $stmt = $this->conexion->prepare("SELECT id_asignacion FROM learning_asignaciones WHERE id_usuario=:u AND estado='completada'");
        $stmt->execute([':u'=>$idUsuario]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) $this->emitirPorAsignacion((int)$id);
    }

    public function sincronizarCompletadas(): void
    {
        if (!$this->tablaDisponible()) return;
        $stmt = $this->conexion->query("SELECT id_asignacion FROM learning_asignaciones WHERE estado='completada'");
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) $this->emitirPorAsignacion((int)$id);
    }

    public function listarUsuario(int $idUsuario): array
    {
        $this->sincronizarUsuario($idUsuario);
        if (!$this->tablaDisponible()) return [];
        $stmt = $this->conexion->prepare("SELECT * FROM learning_certificados WHERE id_usuario=:u ORDER BY fecha_emision DESC,id_certificado DESC");
        $stmt->execute([':u'=>$idUsuario]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarAdmin(string $buscar='', string $estado=''): array
    {
        $this->sincronizarCompletadas();
        if (!$this->tablaDisponible()) return [];
        $sql = "SELECT * FROM learning_certificados WHERE 1=1";
        $params=[];
        if ($buscar !== '') {
            $sql .= " AND (nombre_participante LIKE :b1 OR curso_titulo LIKE :b2 OR capacitacion_nombre LIKE :b3 OR codigo LIKE :b4)";
            $term='%'.$buscar.'%';
            $params=[':b1'=>$term,':b2'=>$term,':b3'=>$term,':b4'=>$term];
        }
        if (in_array($estado,['valido','anulado'],true)) {
            $sql .= " AND estado=:estado";
            $params[':estado']=$estado;
        }
        $sql .= " ORDER BY fecha_emision DESC,id_certificado DESC";
        $stmt=$this->conexion->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId(int $id): ?array
    {
        if ($id <= 0 || !$this->tablaDisponible()) return null;
        $stmt=$this->conexion->prepare("SELECT * FROM learning_certificados WHERE id_certificado=:id LIMIT 1");
        $stmt->execute([':id'=>$id]);
        $r=$stmt->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    public function obtenerPorCodigo(string $codigo): ?array
    {
        if (!$this->tablaDisponible()) return null;
        $codigo=trim($codigo);
        if ($codigo==='') return null;
        $stmt=$this->conexion->prepare("SELECT * FROM learning_certificados WHERE codigo=:codigo LIMIT 1");
        $stmt->execute([':codigo'=>$codigo]);
        $r=$stmt->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    public function cambiarEstado(int $idCertificado, string $estado, string $motivo=''): bool
    {
        if (!$this->tablaDisponible() || $idCertificado<=0 || !in_array($estado,['valido','anulado'],true)) return false;
        if ($estado==='anulado') {
            $stmt=$this->conexion->prepare("UPDATE learning_certificados SET estado='anulado',motivo_anulacion=:motivo,fecha_anulacion=NOW() WHERE id_certificado=:id");
            return $stmt->execute([':motivo'=>(function_exists('mb_substr') ? mb_substr(trim($motivo),0,500,'UTF-8') : substr(trim($motivo),0,500)),':id'=>$idCertificado]);
        }
        $stmt=$this->conexion->prepare("UPDATE learning_certificados SET estado='valido',motivo_anulacion=NULL,fecha_anulacion=NULL WHERE id_certificado=:id");
        return $stmt->execute([':id'=>$idCertificado]);
    }
}
