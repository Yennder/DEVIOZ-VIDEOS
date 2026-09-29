<?php

require_once __DIR__ . '/../config/conexion.php';

class DescargaAutorizada
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
            $stmt = $this->conexion->query("SHOW TABLES LIKE 'descarga_codigos'");
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    public function solicitudesDisponibles(): bool
    {
        try {
            $stmt = $this->conexion->query("SHOW TABLES LIKE 'descarga_solicitudes'");
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    public function usuariosActivos(): array
    {
        $stmt = $this->conexion->query("SELECT id_usuario, nombre, email FROM usuarios WHERE estado = 1 AND rol = 'usuario' ORDER BY nombre");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function administradoresActivos(): array
    {
        $stmt = $this->conexion->query("SELECT id_usuario, nombre, email FROM usuarios WHERE estado = 1 AND rol = 'admin' ORDER BY nombre");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function videosPublicados(): array
    {
        $stmt = $this->conexion->query("SELECT id_video, titulo, tipo_contenido FROM videos WHERE estado = 'publicado' ORDER BY titulo");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function codigoAleatorio(): string
    {
        $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $bloque = '';
        for ($i = 0; $i < 8; $i++) {
            $bloque .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
        }
        return 'DEV-' . substr($bloque, 0, 4) . '-' . substr($bloque, 4, 4);
    }

    private function crearRegistroCodigo(int $idUsuario, int $idVideo, int $creadoPor, int $horasValidez, int $maxUsos): array
    {
        $horasValidez = max(1, min(720, $horasValidez));
        $maxUsos = max(1, min(20, $maxUsos));

        for ($intento = 0; $intento < 8; $intento++) {
            $codigo = $this->codigoAleatorio();
            $hash = hash('sha256', strtoupper($codigo));
            $mascara = 'DEV-****-' . substr($codigo, -4);
            $expira = date('Y-m-d H:i:s', time() + ($horasValidez * 3600));

            try {
                $stmt = $this->conexion->prepare("INSERT INTO descarga_codigos
                    (codigo_hash, codigo_mascara, id_usuario, id_video, creado_por, expira_en, max_usos)
                    VALUES (:hash, :mascara, :usuario, :video, :admin, :expira, :max_usos)");
                $stmt->execute([
                    ':hash' => $hash,
                    ':mascara' => $mascara,
                    ':usuario' => $idUsuario,
                    ':video' => $idVideo,
                    ':admin' => $creadoPor,
                    ':expira' => $expira,
                    ':max_usos' => $maxUsos,
                ]);
                return [
                    'id_codigo' => (int)$this->conexion->lastInsertId(),
                    'codigo' => $codigo,
                    'mascara' => $mascara,
                    'expira_en' => $expira,
                    'max_usos' => $maxUsos,
                ];
            } catch (PDOException $e) {
                if (($e->errorInfo[1] ?? 0) !== 1062) {
                    throw $e;
                }
            }
        }

        throw new RuntimeException('No fue posible generar un codigo unico.');
    }

    public function crear(int $idUsuario, int $idVideo, int $creadoPor, int $horasValidez = 24, int $maxUsos = 1): string
    {
        return $this->crearRegistroCodigo($idUsuario, $idVideo, $creadoPor, $horasValidez, $maxUsos)['codigo'];
    }

    public function crearSolicitud(int $idUsuario, int $idVideo, string $motivo = ''): int
    {
        if (!$this->solicitudesDisponibles()) {
            throw new RuntimeException('El flujo de solicitudes de descarga aun no esta instalado.');
        }

        $stmt = $this->conexion->prepare("SELECT id_video FROM videos WHERE id_video=:video AND estado='publicado' LIMIT 1");
        $stmt->execute([':video' => $idVideo]);
        if (!$stmt->fetchColumn()) {
            throw new RuntimeException('El video no esta disponible para solicitar una descarga.');
        }

        $stmt = $this->conexion->prepare("SELECT id_solicitud FROM descarga_solicitudes
            WHERE id_usuario=:usuario AND id_video=:video AND estado='pendiente'
            ORDER BY id_solicitud DESC LIMIT 1");
        $stmt->execute([':usuario' => $idUsuario, ':video' => $idVideo]);
        $existente = (int)$stmt->fetchColumn();
        if ($existente > 0) {
            return $existente;
        }

        $stmt = $this->conexion->prepare("SELECT s.id_solicitud
            FROM descarga_solicitudes s
            INNER JOIN descarga_codigos dc ON dc.id_codigo=s.id_codigo
            WHERE s.id_usuario=:usuario AND s.id_video=:video AND s.estado='aprobada'
              AND dc.estado='activo' AND dc.expira_en>=NOW() AND dc.usos<dc.max_usos
            ORDER BY s.id_solicitud DESC LIMIT 1");
        $stmt->execute([':usuario' => $idUsuario, ':video' => $idVideo]);
        if ((int)$stmt->fetchColumn() > 0) {
            throw new RuntimeException('Ya tienes una autorizacion de descarga disponible para este video.');
        }

        $motivo = mb_substr(trim($motivo), 0, 500);
        $stmt = $this->conexion->prepare("INSERT INTO descarga_solicitudes
            (id_usuario,id_video,motivo,estado) VALUES (:usuario,:video,:motivo,'pendiente')");
        $stmt->execute([
            ':usuario' => $idUsuario,
            ':video' => $idVideo,
            ':motivo' => $motivo !== '' ? $motivo : null,
        ]);
        $idSolicitud = (int)$this->conexion->lastInsertId();

        return $idSolicitud;
    }

    public function solicitudActualUsuario(int $idUsuario, int $idVideo): ?array
    {
        if (!$this->solicitudesDisponibles()) {
            return null;
        }
        $stmt = $this->conexion->prepare("SELECT s.*, dc.codigo_mascara, dc.expira_en, dc.max_usos, dc.usos,
                    CASE
                      WHEN dc.id_codigo IS NULL THEN NULL
                      WHEN dc.estado='revocado' THEN 'revocado'
                      WHEN dc.expira_en < NOW() THEN 'vencido'
                      WHEN dc.usos >= dc.max_usos THEN 'agotado'
                      ELSE 'disponible'
                    END AS estado_codigo,
                    CASE
                      WHEN s.estado='pendiente' THEN 'pendiente'
                      WHEN s.estado='rechazada' THEN 'rechazada'
                      WHEN dc.id_codigo IS NULL THEN 'aprobada'
                      WHEN dc.estado='revocado' THEN 'revocada'
                      WHEN dc.expira_en < NOW() THEN 'vencida'
                      WHEN dc.usos >= dc.max_usos THEN 'descargada'
                      ELSE 'aprobada'
                    END AS estado_mostrado
            FROM descarga_solicitudes s
            LEFT JOIN descarga_codigos dc ON dc.id_codigo=s.id_codigo
            WHERE s.id_usuario=:usuario AND s.id_video=:video
            ORDER BY s.id_solicitud DESC LIMIT 1");
        $stmt->execute([':usuario' => $idUsuario, ':video' => $idVideo]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    public function contarSolicitudesPendientes(): int
    {
        if (!$this->solicitudesDisponibles()) {
            return 0;
        }
        return (int)$this->conexion->query("SELECT COUNT(*) FROM descarga_solicitudes WHERE estado='pendiente'")->fetchColumn();
    }

    public function listarSolicitudes(string $buscar = '', string $estado = ''): array
    {
        if (!$this->solicitudesDisponibles()) {
            return [];
        }
        $sql = "SELECT s.*,u.nombre AS usuario,u.email,v.titulo AS video,
                       a.nombre AS administrador,dc.codigo_mascara,dc.expira_en,dc.usos,dc.max_usos,dc.estado AS estado_codigo_real,
                       CASE
                         WHEN s.estado='pendiente' THEN 'pendiente'
                         WHEN s.estado='rechazada' THEN 'rechazada'
                         WHEN dc.estado='revocado' THEN 'revocada'
                         WHEN dc.expira_en < NOW() THEN 'vencida'
                         WHEN dc.usos >= dc.max_usos THEN 'descargada'
                         ELSE 'aprobada'
                       END AS estado_mostrado
                FROM descarga_solicitudes s
                INNER JOIN usuarios u ON u.id_usuario=s.id_usuario
                INNER JOIN videos v ON v.id_video=s.id_video
                LEFT JOIN usuarios a ON a.id_usuario=s.revisado_por
                LEFT JOIN descarga_codigos dc ON dc.id_codigo=s.id_codigo
                WHERE 1=1";
        $params = [];
        if ($buscar !== '') {
            $sql .= " AND (u.nombre LIKE :buscar OR u.email LIKE :buscar OR v.titulo LIKE :buscar)";
            $params[':buscar'] = '%' . $buscar . '%';
        }
        if (in_array($estado, ['pendiente','aprobada','rechazada'], true)) {
            $sql .= " AND s.estado=:estado";
            $params[':estado'] = $estado;
        }
        $sql .= " ORDER BY FIELD(s.estado,'pendiente','aprobada','rechazada'), s.fecha_solicitud DESC LIMIT 250";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerSolicitud(int $idSolicitud): ?array
    {
        if (!$this->solicitudesDisponibles()) {
            return null;
        }
        $stmt = $this->conexion->prepare("SELECT s.*,u.nombre AS usuario,u.email,v.titulo AS video,
                    a.nombre AS administrador,dc.codigo_mascara,dc.expira_en,dc.usos,dc.max_usos,dc.estado AS estado_codigo_real,
                    CASE
                      WHEN s.estado='pendiente' THEN 'pendiente'
                      WHEN s.estado='rechazada' THEN 'rechazada'
                      WHEN dc.estado='revocado' THEN 'revocada'
                      WHEN dc.expira_en < NOW() THEN 'vencida'
                      WHEN dc.usos >= dc.max_usos THEN 'descargada'
                      ELSE 'aprobada'
                    END AS estado_mostrado
            FROM descarga_solicitudes s
            INNER JOIN usuarios u ON u.id_usuario=s.id_usuario
            INNER JOIN videos v ON v.id_video=s.id_video
            LEFT JOIN usuarios a ON a.id_usuario=s.revisado_por
            LEFT JOIN descarga_codigos dc ON dc.id_codigo=s.id_codigo
            WHERE s.id_solicitud=:id LIMIT 1");
        $stmt->execute([':id' => $idSolicitud]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    public function listarSolicitudesUsuario(int $idUsuario, int $limite = 100): array
    {
        if (!$this->solicitudesDisponibles() || $idUsuario <= 0) {
            return [];
        }
        $limite = max(1, min(200, $limite));
        $stmt = $this->conexion->prepare("SELECT s.*,v.titulo AS video,v.id_video,dc.codigo_mascara,dc.expira_en,dc.usos,dc.max_usos,dc.estado AS estado_codigo_real,
                    CASE
                      WHEN s.estado='pendiente' THEN 'pendiente'
                      WHEN s.estado='rechazada' THEN 'rechazada'
                      WHEN dc.estado='revocado' THEN 'revocada'
                      WHEN dc.expira_en < NOW() THEN 'vencida'
                      WHEN dc.usos >= dc.max_usos THEN 'descargada'
                      ELSE 'aprobada'
                    END AS estado_mostrado
            FROM descarga_solicitudes s
            INNER JOIN videos v ON v.id_video=s.id_video
            LEFT JOIN descarga_codigos dc ON dc.id_codigo=s.id_codigo
            WHERE s.id_usuario=:usuario
            ORDER BY s.id_solicitud DESC LIMIT {$limite}");
        $stmt->execute([':usuario' => $idUsuario]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function mensajesSolicitud(int $idSolicitud): array
    {
        if (!$this->solicitudesDisponibles()) {
            return [];
        }
        $stmt = $this->conexion->prepare("SELECT m.*,u.nombre AS autor
            FROM descarga_solicitud_mensajes m
            LEFT JOIN usuarios u ON u.id_usuario=m.id_usuario
            WHERE m.id_solicitud=:id ORDER BY m.id_mensaje ASC");
        $stmt->execute([':id' => $idSolicitud]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function agregarMensaje(int $idSolicitud, int $idUsuario, string $rol, string $mensaje): int
    {
        $rol = in_array($rol, ['usuario','admin','sistema'], true) ? $rol : 'usuario';
        $mensaje = mb_substr(trim($mensaje), 0, 1200);
        if ($mensaje === '') {
            return 0;
        }
        $stmt = $this->conexion->prepare("INSERT INTO descarga_solicitud_mensajes
            (id_solicitud,id_usuario,rol,mensaje) VALUES (:solicitud,:usuario,:rol,:mensaje)");
        $stmt->execute([
            ':solicitud' => $idSolicitud,
            ':usuario' => $idUsuario > 0 ? $idUsuario : null,
            ':rol' => $rol,
            ':mensaje' => $mensaje,
        ]);
        return (int)$this->conexion->lastInsertId();
    }

    public function aprobarSolicitud(int $idSolicitud, int $admin, int $horasValidez = 24, int $maxUsos = 1): array
    {
        $this->conexion->beginTransaction();
        try {
            $stmt = $this->conexion->prepare("SELECT * FROM descarga_solicitudes WHERE id_solicitud=:id LIMIT 1 FOR UPDATE");
            $stmt->execute([':id' => $idSolicitud]);
            $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$solicitud) {
                throw new RuntimeException('La solicitud no existe.');
            }
            if (($solicitud['estado'] ?? '') !== 'pendiente') {
                throw new RuntimeException('La solicitud ya fue revisada.');
            }

            $codigo = $this->crearRegistroCodigo(
                (int)$solicitud['id_usuario'],
                (int)$solicitud['id_video'],
                $admin,
                $horasValidez,
                $maxUsos
            );

            $stmt = $this->conexion->prepare("UPDATE descarga_solicitudes
                SET estado='aprobada',id_codigo=:codigo,codigo_visible=:codigo_visible,revisado_por=:admin,fecha_revision=NOW(),motivo_rechazo=NULL
                WHERE id_solicitud=:id");
            $stmt->execute([
                ':codigo' => $codigo['id_codigo'],
                ':codigo_visible' => $codigo['codigo'],
                ':admin' => $admin,
                ':id' => $idSolicitud,
            ]);

            $this->conexion->commit();
            return array_merge($solicitud, $codigo);
        } catch (Throwable $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $e;
        }
    }

    public function rechazarSolicitud(int $idSolicitud, int $admin, string $motivo = ''): array
    {
        $motivo = mb_substr(trim($motivo), 0, 500);
        $stmt = $this->conexion->prepare("SELECT * FROM descarga_solicitudes WHERE id_solicitud=:id LIMIT 1");
        $stmt->execute([':id' => $idSolicitud]);
        $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$solicitud) {
            throw new RuntimeException('La solicitud no existe.');
        }
        if (($solicitud['estado'] ?? '') !== 'pendiente') {
            throw new RuntimeException('La solicitud ya fue revisada.');
        }
        $stmt = $this->conexion->prepare("UPDATE descarga_solicitudes
            SET estado='rechazada',codigo_visible=NULL,revisado_por=:admin,fecha_revision=NOW(),motivo_rechazo=:motivo
            WHERE id_solicitud=:id");
        $stmt->execute([':admin' => $admin, ':motivo' => $motivo !== '' ? $motivo : null, ':id' => $idSolicitud]);
        return $solicitud;
    }

    public function listar(string $buscar = '', string $estado = ''): array
    {
        $sql = "SELECT dc.*, u.nombre AS usuario, u.email,
                       v.titulo AS video, a.nombre AS administrador,
                       CASE
                         WHEN dc.estado = 'revocado' THEN 'revocado'
                         WHEN dc.expira_en < NOW() THEN 'vencido'
                         WHEN dc.usos >= dc.max_usos THEN 'agotado'
                         ELSE 'disponible'
                       END AS estado_calculado
                FROM descarga_codigos dc
                INNER JOIN usuarios u ON u.id_usuario = dc.id_usuario
                INNER JOIN videos v ON v.id_video = dc.id_video
                INNER JOIN usuarios a ON a.id_usuario = dc.creado_por
                WHERE 1=1";
        $params = [];

        if ($buscar !== '') {
            $sql .= " AND (u.nombre LIKE :buscar OR u.email LIKE :buscar OR v.titulo LIKE :buscar OR dc.codigo_mascara LIKE :buscar)";
            $params[':buscar'] = '%' . $buscar . '%';
        }

        if (in_array($estado, ['disponible', 'revocado', 'vencido', 'agotado'], true)) {
            if ($estado === 'revocado') {
                $sql .= " AND dc.estado = 'revocado'";
            } elseif ($estado === 'vencido') {
                $sql .= " AND dc.estado = 'activo' AND dc.expira_en < NOW()";
            } elseif ($estado === 'agotado') {
                $sql .= " AND dc.estado = 'activo' AND dc.expira_en >= NOW() AND dc.usos >= dc.max_usos";
            } else {
                $sql .= " AND dc.estado = 'activo' AND dc.expira_en >= NOW() AND dc.usos < dc.max_usos";
            }
        }

        $sql .= " ORDER BY dc.id_codigo DESC LIMIT 250";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function historial(int $limite = 100): array
    {
        $limite = max(1, min(250, $limite));
        $sql = "SELECT dl.*, u.nombre AS usuario, u.email, v.titulo AS video, dc.codigo_mascara
                FROM descarga_logs dl
                INNER JOIN usuarios u ON u.id_usuario = dl.id_usuario
                INNER JOIN videos v ON v.id_video = dl.id_video
                INNER JOIN descarga_codigos dc ON dc.id_codigo = dl.id_codigo
                ORDER BY dl.id_log DESC
                LIMIT " . $limite;
        $stmt = $this->conexion->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function cambiarEstado(int $idCodigo, string $estado): bool
    {
        if (!in_array($estado, ['activo', 'revocado'], true)) {
            return false;
        }
        $stmt = $this->conexion->prepare("UPDATE descarga_codigos SET estado = :estado WHERE id_codigo = :id");
        return $stmt->execute([':estado' => $estado, ':id' => $idCodigo]);
    }

    public function validarYConsumir(string $codigo, int $idUsuario, int $idVideo, string $ip, string $userAgent): array
    {
        $codigo = strtoupper(trim($codigo));
        $hash = hash('sha256', $codigo);

        $this->conexion->beginTransaction();
        try {
            $stmt = $this->conexion->prepare("SELECT dc.*, v.titulo, v.archivo_video, v.estado AS video_estado
                FROM descarga_codigos dc
                INNER JOIN videos v ON v.id_video = dc.id_video
                WHERE dc.codigo_hash = :hash
                LIMIT 1 FOR UPDATE");
            $stmt->execute([':hash' => $hash]);
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$fila) {
                throw new RuntimeException('El codigo ingresado no es valido.');
            }
            if ((int)$fila['id_usuario'] !== $idUsuario || (int)$fila['id_video'] !== $idVideo) {
                throw new RuntimeException('Este codigo no corresponde a tu usuario o a este video.');
            }
            if (($fila['estado'] ?? '') !== 'activo') {
                throw new RuntimeException('Este codigo fue revocado por el administrador.');
            }
            if (strtotime((string)$fila['expira_en']) < time()) {
                throw new RuntimeException('Este codigo ya vencio. Solicita uno nuevo al administrador.');
            }
            if ((int)$fila['usos'] >= (int)$fila['max_usos']) {
                throw new RuntimeException('Este codigo ya alcanzo el numero maximo de descargas.');
            }
            if (($fila['video_estado'] ?? '') !== 'publicado') {
                throw new RuntimeException('El video ya no esta disponible para descarga.');
            }

            $ruta = __DIR__ . '/../uploads/videos/' . basename((string)$fila['archivo_video']);
            if (!is_file($ruta) || !is_readable($ruta)) {
                throw new RuntimeException('El archivo del video no esta disponible en el servidor.');
            }

            $update = $this->conexion->prepare("UPDATE descarga_codigos
                SET usos = usos + 1, ultimo_uso = NOW()
                WHERE id_codigo = :id");
            $update->execute([':id' => (int)$fila['id_codigo']]);

            $log = $this->conexion->prepare("INSERT INTO descarga_logs
                (id_codigo, id_usuario, id_video, ip, user_agent)
                VALUES (:codigo, :usuario, :video, :ip, :ua)");
            $log->execute([
                ':codigo' => (int)$fila['id_codigo'],
                ':usuario' => $idUsuario,
                ':video' => $idVideo,
                ':ip' => substr($ip, 0, 45),
                ':ua' => substr($userAgent, 0, 255),
            ]);

            $this->conexion->commit();
            $fila['ruta_archivo'] = $ruta;
            return $fila;
        } catch (Throwable $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $e;
        }
    }
}
