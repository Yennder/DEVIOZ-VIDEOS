<?php
/**
 * DEVIOZ V5.2 - Watch & Build.
 * Los retos practicos y sus calificaciones son EVIDENCIA separada del progreso de
 * Learning Lab, del SkillMapa academico y de la evaluacion del supervisor.
 */
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/configuracion.php';

final class WatchBuild
{
    private PDO $db;
    private const EXT_MIME = [
        'pdf' => ['application/pdf'],
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'webp' => ['image/webp'],
        'zip' => ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'],
    ];
    public const MAX_FILE_BYTES = 10485760; // 10 MiB

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? (new Conexion())->conectar();
    }

    public static function storagePath(): string
    {
        return dirname(__DIR__) . '/storage/watchbuild/';
    }

    public function instalado(): bool
    {
        try {
            $a = $this->db->query("SHOW TABLES LIKE 'watchbuild_retos'")->fetchColumn();
            $b = $this->db->query("SHOW TABLES LIKE 'watchbuild_entregas'")->fetchColumn();
            return $a !== false && $b !== false;
        } catch (Throwable $e) {
            return false;
        }
    }

    private static function longitud(string $texto): int
    {
        return function_exists('mb_strlen') ? mb_strlen($texto, 'UTF-8') : strlen($texto);
    }

    private static function texto(mixed $value, int $max): string
    {
        $value = trim((string)$value);
        if (self::longitud($value) > $max) {
            throw new InvalidArgumentException('El texto excede la longitud permitida.');
        }
        return $value;
    }

    public function opciones(): array
    {
        return [
            'videos' => $this->db->query("SELECT id_video,titulo FROM videos WHERE estado='publicado' ORDER BY id_video DESC")->fetchAll(PDO::FETCH_ASSOC),
            'cursos' => $this->db->query("SELECT id_curso,titulo FROM learning_cursos WHERE estado='publicado' ORDER BY titulo")->fetchAll(PDO::FETCH_ASSOC),
            'skills' => $this->db->query("SELECT id_skill,nombre,icono FROM learning_skills WHERE estado=1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    private function comprobarRelacionado(string $tabla, string $columna, int $id, string $extra = ''): void
    {
        $stmt = $this->db->prepare("SELECT 1 FROM {$tabla} WHERE {$columna}=? {$extra} LIMIT 1");
        $stmt->execute([$id]);
        if (!$stmt->fetchColumn()) {
            throw new InvalidArgumentException('La relación seleccionada ya no está disponible.');
        }
    }

    public function reto(int $id, bool $soloPublicado = false): ?array
    {
        $sql = "SELECT r.*, v.titulo AS video_titulo, c.titulo AS curso_titulo, s.nombre AS skill_nombre, s.icono AS skill_icono,
                       (SELECT COUNT(*) FROM watchbuild_entregas e WHERE e.id_reto=r.id_reto) AS total_entregas
                FROM watchbuild_retos r
                LEFT JOIN videos v ON v.id_video=r.id_video
                LEFT JOIN learning_cursos c ON c.id_curso=r.id_curso
                LEFT JOIN learning_skills s ON s.id_skill=r.id_skill
                WHERE r.id_reto=?" . ($soloPublicado ? " AND r.estado='publicado'" : '') . ' LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function retosAdmin(string $buscar = '', string $estado = ''): array
    {
        $buscar = trim($buscar);
        $estado = in_array($estado, ['borrador','publicado','archivado'], true) ? $estado : '';
        $stmt = $this->db->prepare("SELECT r.*, s.nombre AS skill_nombre, c.titulo AS curso_titulo,
            (SELECT COUNT(DISTINCT id_usuario) FROM watchbuild_entregas e WHERE e.id_reto=r.id_reto) AS participantes,
            (SELECT COUNT(*) FROM watchbuild_entregas e WHERE e.id_reto=r.id_reto AND e.estado='enviada') AS por_revisar
            FROM watchbuild_retos r
            LEFT JOIN learning_skills s ON s.id_skill=r.id_skill
            LEFT JOIN learning_cursos c ON c.id_curso=r.id_curso
            WHERE (?='' OR r.estado=?) AND (?='' OR r.titulo LIKE ?)
            ORDER BY r.id_reto DESC LIMIT 200");
        $stmt->execute([$estado, $estado, $buscar, '%'.$buscar.'%']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function retosPublicos(int $usuario, string $buscar = '', int $skill = 0): array
    {
        $buscar = trim($buscar);
        $stmt = $this->db->prepare("SELECT r.*, c.titulo AS curso_titulo, v.titulo AS video_titulo,
            s.nombre AS skill_nombre, s.icono AS skill_icono,
            e.estado AS mi_estado, e.nota AS mi_nota, e.numero_intento AS mis_intentos
            FROM watchbuild_retos r
            LEFT JOIN learning_cursos c ON c.id_curso=r.id_curso
            LEFT JOIN videos v ON v.id_video=r.id_video
            LEFT JOIN learning_skills s ON s.id_skill=r.id_skill
            LEFT JOIN watchbuild_entregas e ON e.id_entrega=(
                SELECT MAX(ee.id_entrega) FROM watchbuild_entregas ee
                WHERE ee.id_reto=r.id_reto AND ee.id_usuario=?
            )
            WHERE (r.estado='publicado' OR e.id_entrega IS NOT NULL) AND (?=0 OR r.id_skill=?)
              AND (?='' OR r.titulo LIKE ? OR r.descripcion LIKE ?)
            ORDER BY CASE WHEN r.fecha_limite IS NULL THEN 1 ELSE 0 END, r.fecha_limite ASC, r.id_reto DESC LIMIT 200");
        $stmt->execute([$usuario, $skill, $skill, $buscar, '%'.$buscar.'%', '%'.$buscar.'%']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Retos sugeridos a partir de la leccion o curso que el participante consulta. */
    public function relacionados(int $idVideo = 0, int $idCurso = 0): array
    {
        if ($idVideo < 1 && $idCurso < 1) return [];
        $stmt = $this->db->prepare("SELECT id_reto,titulo,dificultad,fecha_limite,nota_minima
            FROM watchbuild_retos WHERE estado='publicado'
            AND ((id_video IS NOT NULL AND id_video=?) OR (id_curso IS NOT NULL AND id_curso=?))
            AND (fecha_limite IS NULL OR fecha_limite >= NOW())
            ORDER BY fecha_limite IS NULL,fecha_limite,id_reto DESC LIMIT 3");
        $stmt->execute([$idVideo,$idCurso]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function guardarReto(array $datos, int $admin, int $id = 0): int
    {
        $titulo = self::texto($datos['titulo'] ?? '', 180);
        $descripcion = self::texto($datos['descripcion'] ?? '', 3000);
        $instrucciones = self::texto($datos['instrucciones'] ?? '', 18000);
        $criterios = self::texto($datos['criterios'] ?? '', 6000);
        if (self::longitud($titulo) < 6 || self::longitud($descripcion) < 15 || self::longitud($instrucciones) < 25 || self::longitud($criterios) < 10) {
            throw new InvalidArgumentException('Completa el título, descripción, instrucciones y criterios de evaluación.');
        }
        $estado = (string)($datos['estado'] ?? 'borrador');
        $nivel = (string)($datos['dificultad'] ?? 'intermedia');
        if (!in_array($estado, ['borrador','publicado','archivado'], true) || !in_array($nivel, ['basica','intermedia','avanzada'], true)) {
            throw new InvalidArgumentException('Estado o dificultad no válida.');
        }
        $notaTexto = (string)($datos['nota_minima'] ?? '70');
        if (!is_numeric($notaTexto) || (float)$notaTexto < 0 || (float)$notaTexto > 100) {
            throw new InvalidArgumentException('La nota mínima debe estar entre 0 y 100.');
        }
        $nota = round((float)$notaTexto, 2);
        $vinculos = [];
        foreach (['id_video','id_curso','id_skill'] as $campo) {
            $raw = trim((string)($datos[$campo] ?? ''));
            if ($raw !== '' && (!ctype_digit($raw) || (int)$raw < 1)) {
                throw new InvalidArgumentException('Los vínculos del reto deben ser válidos.');
            }
            $vinculos[$campo] = $raw === '' ? null : (int)$raw;
        }
        if (!array_filter($vinculos)) {
            throw new InvalidArgumentException('Vincula el reto con al menos un video, curso o Skill.');
        }
        if ($vinculos['id_video']) $this->comprobarRelacionado('videos', 'id_video', $vinculos['id_video'], "AND estado='publicado'");
        if ($vinculos['id_curso']) $this->comprobarRelacionado('learning_cursos', 'id_curso', $vinculos['id_curso'], "AND estado='publicado'");
        if ($vinculos['id_skill']) $this->comprobarRelacionado('learning_skills', 'id_skill', $vinculos['id_skill'], 'AND estado=1');

        $limite = trim((string)($datos['fecha_limite'] ?? ''));
        if ($limite !== '') {
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $limite);
            if (!$parsed || $parsed->format('Y-m-d\TH:i') !== $limite) throw new InvalidArgumentException('La fecha límite no es válida.');
            $limite = $parsed->format('Y-m-d H:i:s');
        }
        if ($estado === 'publicado' && $limite && $limite < date('Y-m-d H:i:s')) {
            throw new InvalidArgumentException('No puedes publicar un reto que ya venció.');
        }
        $params = [$titulo, $descripcion, $instrucciones, $criterios, $vinculos['id_video'], $vinculos['id_curso'], $vinculos['id_skill'], $nivel, $nota, $limite ?: null, $estado];
        if ($id > 0) {
            try {
                $this->db->beginTransaction();
                // La creacion de entregas tambien bloquea este reto antes del INSERT.
                // Por eso nadie puede cambiar la rubrica mientras entra la primera entrega.
                $stmt = $this->db->prepare('SELECT * FROM watchbuild_retos WHERE id_reto=? FOR UPDATE');
                $stmt->execute([$id]);
                $previo = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$previo) throw new InvalidArgumentException('El reto no existe.');
                $stmt = $this->db->prepare('SELECT COUNT(*) FROM watchbuild_entregas WHERE id_reto=?');
                $stmt->execute([$id]);
                if ((int)$stmt->fetchColumn() > 0) {
                    foreach (['instrucciones'=>$instrucciones,'criterios'=>$criterios,'nota_minima'=>$nota,'id_video'=>$vinculos['id_video'],'id_curso'=>$vinculos['id_curso'],'id_skill'=>$vinculos['id_skill']] as $key=>$nuevo) {
                        if ($key === 'nota_minima') {
                            if (abs((float)$previo[$key] - (float)$nuevo) > 0.001) throw new InvalidArgumentException('Este reto ya tiene entregas. Para cambiar criterios, nota mínima o vínculos crea otro reto.');
                        } elseif ((string)($previo[$key] ?? '') !== (string)($nuevo ?? '')) {
                            throw new InvalidArgumentException('Este reto ya tiene entregas. Para cambiar criterios, nota mínima o vínculos crea otro reto.');
                        }
                    }
                }
                $stmt=$this->db->prepare('UPDATE watchbuild_retos SET titulo=?,descripcion=?,instrucciones=?,criterios=?,id_video=?,id_curso=?,id_skill=?,dificultad=?,nota_minima=?,fecha_limite=?,estado=? WHERE id_reto=?');
                $stmt->execute([...$params, $id]);
                $this->db->commit();
                return $id;
            } catch (Throwable $e) {
                if ($this->db->inTransaction()) $this->db->rollBack();
                throw $e;
            }
        }
        $stmt = $this->db->prepare('INSERT INTO watchbuild_retos (titulo,descripcion,instrucciones,criterios,id_video,id_curso,id_skill,dificultad,nota_minima,fecha_limite,estado,creado_por) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([...$params, $admin]);
        return (int)$this->db->lastInsertId();
    }

    public function historial(int $idReto, int $usuario): array
    {
        $stmt=$this->db->prepare('SELECT * FROM watchbuild_entregas WHERE id_reto=? AND id_usuario=? ORDER BY numero_intento DESC');
        $stmt->execute([$idReto, $usuario]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function ultimaEntrega(int $idReto, int $usuario): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM watchbuild_entregas WHERE id_reto=? AND id_usuario=? ORDER BY numero_intento DESC LIMIT 1');
        $stmt->execute([$idReto,$usuario]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function puedeEnviar(array $reto, ?array $ultima): bool
    {
        if ($reto['estado'] !== 'publicado' || ($reto['fecha_limite'] && strtotime($reto['fecha_limite']) < time())) return false;
        return !$ultima || in_array((string)$ultima['estado'], ['correcciones','no_aprobada'], true);
    }

    public static function validarArchivo(?array $archivo): ?array
    {
        if (!$archivo || (int)($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
        if ((int)$archivo['error'] !== UPLOAD_ERR_OK) throw new InvalidArgumentException('No pudo subirse el archivo. Revisa el límite de carga de PHP.');
        if (!is_uploaded_file((string)$archivo['tmp_name'])) throw new InvalidArgumentException('La carga del archivo no es válida.');
        $size = (int)($archivo['size'] ?? 0);
        if ($size < 1 || $size > self::MAX_FILE_BYTES) throw new InvalidArgumentException('El archivo debe pesar menos de 10 MB.');
        $nombre = (string)($archivo['name'] ?? '');
        $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        if (!isset(self::EXT_MIME[$ext])) throw new InvalidArgumentException('Solo se aceptan PDF, JPG, PNG, WEBP o ZIP.');
        // El ZIP debe ser un contenedor real; MIME octet-stream, por si solo,
        // no garantiza que los bytes sean un archivo ZIP.
        if ($ext === 'zip') {
            $fp = fopen((string)$archivo['tmp_name'], 'rb');
            $signature = $fp ? fread($fp, 4) : '';
            if ($fp) fclose($fp);
            if (!in_array($signature, ["PK\x03\x04", "PK\x05\x06", "PK\x07\x08"], true)) {
                throw new InvalidArgumentException('El ZIP adjunto no tiene una cabecera válida.');
            }
        }
        $mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file((string)$archivo['tmp_name']);
        if (!in_array($mime, self::EXT_MIME[$ext], true)) throw new InvalidArgumentException('El contenido del archivo no coincide con su extensión.');
        return [
            'tmp' => (string)$archivo['tmp_name'], 'ext'=>$ext, 'mime'=>$mime, 'size'=>$size,
            'nombre'=>self::texto(basename(str_replace('\\', '/', $nombre)), 255),
        ];
    }

    public static function validarEnlace(string $enlace): string
    {
        $enlace = trim($enlace);
        if ($enlace === '') return '';
        if (strlen($enlace) > 1024 || filter_var($enlace, FILTER_VALIDATE_URL) === false) throw new InvalidArgumentException('Introduce un enlace http/https válido.');
        $scheme = strtolower((string)parse_url($enlace, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http','https'], true)) throw new InvalidArgumentException('El enlace debe ser HTTP o HTTPS.');
        return $enlace;
    }

    /** Guarda un intento nuevo; no edita ni destruye evidencias anteriores. */
    public function enviar(int $idReto, int $usuario, string $descripcion, string $enlace, ?array $archivo): int
    {
        $descripcion = self::texto($descripcion, 8000);
        $enlace = self::validarEnlace($enlace);
        $carga = self::validarArchivo($archivo);
        if (self::longitud($descripcion) < 20) throw new InvalidArgumentException('Describe tu trabajo en al menos 20 caracteres.');
        if (!$enlace && !$carga) throw new InvalidArgumentException('Adjunta un archivo o proporciona un enlace de evidencia.');
        $archivoGuardado = null;
        try {
            $this->db->beginTransaction();
            // Bloquea al usuario para evitar dos envios simultaneos del mismo reto.
            $stmt = $this->db->prepare('SELECT id_usuario FROM usuarios WHERE id_usuario=? AND estado=1 FOR UPDATE');
            $stmt->execute([$usuario]);
            if (!$stmt->fetchColumn()) throw new InvalidArgumentException('Usuario no disponible.');
            $stmt = $this->db->prepare('SELECT * FROM watchbuild_retos WHERE id_reto=? FOR UPDATE');
            $stmt->execute([$idReto]);
            $reto = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$reto) throw new InvalidArgumentException('No existe este reto.');
            $ultimo = $this->ultimaEntrega($idReto, $usuario);
            if (!$this->puedeEnviar($reto, $ultimo)) throw new InvalidArgumentException('El reto está cerrado, vencido o ya tiene una entrega pendiente/aprobada.');

            if ($carga) {
                $dir = self::storagePath();
                if (!is_dir($dir) || !is_file(dirname($dir).'/.htaccess') || !is_file($dir.'.htaccess') || !is_writable($dir)) {
                    throw new RuntimeException('No se puede guardar la evidencia protegida. Comprueba storage/watchbuild y sus permisos.');
                }
                $archivoGuardado = bin2hex(random_bytes(16)) . '.' . $carga['ext'];
                if (!move_uploaded_file($carga['tmp'], $dir.$archivoGuardado)) {
                    throw new RuntimeException('No fue posible guardar el archivo.');
                }
            }
            $stmt=$this->db->prepare('INSERT INTO watchbuild_entregas (id_reto,id_usuario,numero_intento,descripcion,enlace,archivo_original,archivo_guardado,archivo_mime,archivo_tamano) VALUES (?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$idReto,$usuario,((int)($ultimo['numero_intento'] ?? 0))+1,$descripcion,$enlace ?: null,$carga['nombre'] ?? null,$archivoGuardado,$carga['mime'] ?? null,$carga['size'] ?? null]);
            $id=(int)$this->db->lastInsertId();
            $this->db->commit();
            return $id;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            if ($archivoGuardado && is_file(self::storagePath().$archivoGuardado)) @unlink(self::storagePath().$archivoGuardado);
            throw $e;
        }
    }

    public function entregasAdmin(int $idReto = 0, string $estado = ''): array
    {
        $estado = in_array($estado, ['enviada','correcciones','aprobada','no_aprobada'], true) ? $estado : '';
        $stmt=$this->db->prepare("SELECT e.*,u.nombre AS usuario_nombre,u.email,r.titulo AS reto_titulo,r.nota_minima,
            s.nombre AS skill_nombre FROM watchbuild_entregas e
            JOIN usuarios u ON u.id_usuario=e.id_usuario
            JOIN watchbuild_retos r ON r.id_reto=e.id_reto
            LEFT JOIN learning_skills s ON s.id_skill=r.id_skill
            WHERE (?=0 OR e.id_reto=?) AND (?='' OR e.estado=?) ORDER BY e.fecha_entrega DESC,e.id_entrega DESC LIMIT 300");
        $stmt->execute([$idReto,$idReto,$estado,$estado]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function entrega(int $id): ?array
    {
        $stmt=$this->db->prepare('SELECT e.*,u.nombre AS usuario_nombre,u.email,r.titulo AS reto_titulo,r.nota_minima,r.id_skill,s.nombre AS skill_nombre, revisor.nombre AS revisor_nombre FROM watchbuild_entregas e JOIN usuarios u ON u.id_usuario=e.id_usuario JOIN watchbuild_retos r ON r.id_reto=e.id_reto LEFT JOIN learning_skills s ON s.id_skill=r.id_skill LEFT JOIN usuarios revisor ON revisor.id_usuario=e.revisado_por WHERE e.id_entrega=? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function revisar(int $id, int $admin, string $decision, string $nota, string $comentario): void
    {
        if (!in_array($decision, ['aprobada','correcciones','no_aprobada'], true)) throw new InvalidArgumentException('Selecciona una decisión válida.');
        if (!is_numeric($nota) || (float)$nota < 0 || (float)$nota > 100) throw new InvalidArgumentException('La nota debe estar entre 0 y 100.');
        $puntaje = round((float)$nota,2);
        $comentario=self::texto($comentario, 6000);
        if ($decision !== 'aprobada' && self::longitud($comentario) < 8) throw new InvalidArgumentException('Explica al participante qué debe corregir.');
        try {
            $this->db->beginTransaction();
            $stmt=$this->db->prepare('SELECT e.estado,r.nota_minima FROM watchbuild_entregas e JOIN watchbuild_retos r ON r.id_reto=e.id_reto WHERE e.id_entrega=? FOR UPDATE');
            $stmt->execute([$id]);
            $e=$stmt->fetch(PDO::FETCH_ASSOC);
            if (!$e || $e['estado'] !== 'enviada') throw new InvalidArgumentException('Esta entrega ya fue revisada o no existe.');
            if ($decision === 'aprobada' && $puntaje < (float)$e['nota_minima']) throw new InvalidArgumentException('La nota no alcanza el mínimo para aprobar el reto.');
            $stmt=$this->db->prepare('UPDATE watchbuild_entregas SET estado=?,nota=?,comentario_admin=?,revisado_por=?,fecha_revision=NOW() WHERE id_entrega=? AND estado=\'enviada\'');
            $stmt->execute([$decision,$puntaje,$comentario ?: null,$admin,$id]);
            if ($stmt->rowCount() !== 1) throw new RuntimeException('No se pudo guardar la revisión.');
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /** Evidencias practicas aprobadas: cuenta por reto, no altera la puntuacion de SkillMapa. */
    public function evidenciasSkills(int $usuario): array
    {
        $stmt=$this->db->prepare("SELECT r.id_skill,s.nombre AS skill_nombre,COUNT(DISTINCT r.id_reto) AS aprobados,
            MAX(e.fecha_revision) AS ultima_evidencia FROM watchbuild_entregas e
            JOIN watchbuild_retos r ON r.id_reto=e.id_reto
            JOIN learning_skills s ON s.id_skill=r.id_skill
            WHERE e.id_usuario=? AND e.estado='aprobada' AND r.id_skill IS NOT NULL
            GROUP BY r.id_skill,s.nombre ORDER BY aprobados DESC, s.nombre");
        $stmt->execute([$usuario]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
