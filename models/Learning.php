<?php

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/Notificacion.php';

class Learning
{
    private PDO $conexion;
    private Notificacion $notificaciones;

    public function __construct()
    {
        $db = new Conexion();
        $this->conexion = $db->conectar();
        $this->notificaciones = new Notificacion($this->conexion);
    }

    private function texto($valor, int $max = 0): string
    {
        $valor = trim((string)$valor);
        if ($max > 0) {
            return function_exists('mb_substr') ? mb_substr($valor, 0, $max, 'UTF-8') : substr($valor, 0, $max);
        }
        return $valor;
    }

    public function listarCursosAdmin(string $buscar = '', string $estado = ''): array
    {
        $sql = "
            SELECT c.*,
                   u.nombre AS creador,
                   COUNT(DISTINCT l.id_leccion) AS total_lecciones,
                   COUNT(DISTINCT cap.id_capacitacion) AS total_capacitaciones,
                   COUNT(DISTINCT a.id_asignacion) AS total_asignaciones
            FROM learning_cursos c
            INNER JOIN usuarios u ON u.id_usuario = c.creado_por
            LEFT JOIN learning_curso_lecciones l ON l.id_curso = c.id_curso
            LEFT JOIN learning_capacitaciones cap ON cap.id_curso = c.id_curso
            LEFT JOIN learning_asignaciones a ON a.id_capacitacion = cap.id_capacitacion
            WHERE 1=1
        ";
        $params = [];
        if ($buscar !== '') {
            $sql .= " AND (c.titulo LIKE :buscar OR c.descripcion LIKE :buscar2)";
            $params[':buscar'] = '%' . $buscar . '%';
            $params[':buscar2'] = '%' . $buscar . '%';
        }
        if (in_array($estado, ['borrador','publicado','archivado'], true)) {
            $sql .= " AND c.estado = :estado";
            $params[':estado'] = $estado;
        }
        $sql .= " GROUP BY c.id_curso ORDER BY c.fecha_actualizacion DESC";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarCursosPublicados(): array
    {
        $sql = "
            SELECT c.*,
                   COUNT(DISTINCT l.id_leccion) AS total_lecciones,
                   COALESCE(SUM(CASE WHEN l.obligatoria = 1 THEN 1 ELSE 0 END),0) AS lecciones_obligatorias,
                   MIN(v.miniatura) AS miniatura_referencia
            FROM learning_cursos c
            LEFT JOIN learning_curso_lecciones l ON l.id_curso = c.id_curso
            LEFT JOIN videos v ON v.id_video = l.id_video
            WHERE c.estado = 'publicado'
            GROUP BY c.id_curso
            ORDER BY c.fecha_actualizacion DESC
        ";
        return $this->conexion->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarCurso(int $idCurso): ?array
    {
        $stmt = $this->conexion->prepare("SELECT * FROM learning_cursos WHERE id_curso = :id LIMIT 1");
        $stmt->execute([':id' => $idCurso]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function guardarCurso(array $datos, int $idAdmin): int
    {
        $id = (int)($datos['id_curso'] ?? 0);
        $titulo = $this->texto($datos['titulo'] ?? '', 180);
        if ($titulo === '') {
            throw new InvalidArgumentException('El título del curso es obligatorio.');
        }
        $descripcion = $this->texto($datos['descripcion'] ?? '');
        $nivel = in_array(($datos['nivel'] ?? ''), ['principiante','intermedio','avanzado'], true)
            ? $datos['nivel'] : 'principiante';
        $duracion = max(0, (int)($datos['duracion_estimada_minutos'] ?? 0));
        $secuencial = !empty($datos['orden_secuencial']) ? 1 : 0;
        $estado = in_array(($datos['estado'] ?? ''), ['borrador','publicado','archivado'], true)
            ? $datos['estado'] : 'borrador';

        if ($id > 0) {
            $stmt = $this->conexion->prepare("UPDATE learning_cursos SET titulo=:titulo, descripcion=:descripcion, nivel=:nivel, duracion_estimada_minutos=:duracion, orden_secuencial=:secuencial, estado=:estado WHERE id_curso=:id");
            $stmt->execute([
                ':titulo'=>$titulo, ':descripcion'=>$descripcion, ':nivel'=>$nivel, ':duracion'=>$duracion,
                ':secuencial'=>$secuencial, ':estado'=>$estado, ':id'=>$id
            ]);
            return $id;
        }

        $stmt = $this->conexion->prepare("INSERT INTO learning_cursos (titulo,descripcion,nivel,duracion_estimada_minutos,orden_secuencial,estado,creado_por) VALUES (:titulo,:descripcion,:nivel,:duracion,:secuencial,:estado,:creador)");
        $stmt->execute([
            ':titulo'=>$titulo, ':descripcion'=>$descripcion, ':nivel'=>$nivel, ':duracion'=>$duracion,
            ':secuencial'=>$secuencial, ':estado'=>$estado, ':creador'=>$idAdmin
        ]);
        return (int)$this->conexion->lastInsertId();
    }

    public function eliminarCurso(int $idCurso): bool
    {
        try {
            $stmt = $this->conexion->prepare("DELETE FROM learning_cursos WHERE id_curso = :id");
            return $stmt->execute([':id'=>$idCurso]);
        } catch (PDOException $e) {
            if ((string)$e->getCode() === '23000') {
                return false;
            }
            throw $e;
        }
    }

    public function videosDisponibles(string $buscar = ''): array
    {
        $sql = "SELECT v.id_video,v.titulo,v.tipo_contenido,v.numero_capitulo,v.id_serie,v.id_temporada,v.miniatura,c.nombre AS categoria FROM videos v INNER JOIN categorias c ON c.id_categoria=v.id_categoria WHERE v.estado='publicado'";
        $params = [];
        if ($buscar !== '') {
            $sql .= " AND (v.titulo LIKE :q OR v.descripcion LIKE :q2)";
            $params[':q'] = '%' . $buscar . '%';
            $params[':q2'] = '%' . $buscar . '%';
        }
        $sql .= " ORDER BY v.tipo_contenido, v.id_serie, v.id_temporada, v.numero_capitulo, v.titulo LIMIT 400";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function leccionesCurso(int $idCurso): array
    {
        $sql = "
            SELECT l.*, COALESCE(NULLIF(l.titulo_personalizado,''),v.titulo) AS titulo,
                   v.titulo AS titulo_video, v.descripcion AS descripcion_video, v.miniatura,
                   v.tipo_contenido, v.numero_capitulo, c.nombre AS categoria
            FROM learning_curso_lecciones l
            INNER JOIN videos v ON v.id_video = l.id_video
            INNER JOIN categorias c ON c.id_categoria = v.id_categoria
            WHERE l.id_curso = :curso
            ORDER BY l.orden ASC, l.id_leccion ASC
        ";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute([':curso'=>$idCurso]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function agregarLeccion(int $idCurso, int $idVideo, string $titulo, string $descripcion, int $orden, bool $obligatoria): bool
    {
        if (!$this->buscarCurso($idCurso) || $idVideo <= 0) return false;
        $stmt = $this->conexion->prepare("INSERT INTO learning_curso_lecciones (id_curso,id_video,titulo_personalizado,descripcion,orden,obligatoria) VALUES (:curso,:video,:titulo,:descripcion,:orden,:obligatoria) ON DUPLICATE KEY UPDATE titulo_personalizado=VALUES(titulo_personalizado), descripcion=VALUES(descripcion), orden=VALUES(orden), obligatoria=VALUES(obligatoria)");
        return $stmt->execute([
            ':curso'=>$idCurso, ':video'=>$idVideo, ':titulo'=>$this->texto($titulo,180),
            ':descripcion'=>$this->texto($descripcion,700), ':orden'=>max(1,$orden), ':obligatoria'=>$obligatoria?1:0
        ]);
    }

    public function actualizarLeccion(int $idLeccion, int $idCurso, array $datos): bool
    {
        $stmt = $this->conexion->prepare("UPDATE learning_curso_lecciones SET titulo_personalizado=:titulo, descripcion=:descripcion, orden=:orden, obligatoria=:obligatoria WHERE id_leccion=:id AND id_curso=:curso");
        return $stmt->execute([
            ':titulo'=>$this->texto($datos['titulo_personalizado'] ?? '',180),
            ':descripcion'=>$this->texto($datos['descripcion'] ?? '',700),
            ':orden'=>max(1,(int)($datos['orden'] ?? 1)), ':obligatoria'=>!empty($datos['obligatoria'])?1:0,
            ':id'=>$idLeccion, ':curso'=>$idCurso
        ]);
    }

    public function eliminarLeccion(int $idLeccion, int $idCurso): bool
    {
        $stmt = $this->conexion->prepare("DELETE FROM learning_curso_lecciones WHERE id_leccion=:id AND id_curso=:curso");
        return $stmt->execute([':id'=>$idLeccion, ':curso'=>$idCurso]);
    }

    public function usuariosAsignables(): array
    {
        $stmt = $this->conexion->query("SELECT id_usuario,nombre,email FROM usuarios WHERE estado=1 AND rol='usuario' ORDER BY nombre ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crearCapacitacion(array $datos, array $usuarios, int $idAdmin): int
    {
        $idCurso = (int)($datos['id_curso'] ?? 0);
        $nombre = $this->texto($datos['nombre'] ?? '', 180);
        $inicio = (string)($datos['fecha_inicio'] ?? '');
        $limite = (string)($datos['fecha_limite'] ?? '');
        if ($idCurso <= 0 || $nombre === '' || !$inicio || !$limite) {
            throw new InvalidArgumentException('Curso, nombre y fechas son obligatorios.');
        }
        if ($limite < $inicio) {
            throw new InvalidArgumentException('La fecha límite no puede ser anterior a la fecha de inicio.');
        }
        $estado = in_array(($datos['estado'] ?? ''), ['planificada','activa','cerrada'], true) ? $datos['estado'] : 'activa';
        $this->conexion->beginTransaction();
        try {
            $stmt = $this->conexion->prepare("INSERT INTO learning_capacitaciones (id_curso,nombre,descripcion,fecha_inicio,fecha_limite,estado,creado_por) VALUES (:curso,:nombre,:descripcion,:inicio,:limite,:estado,:creador)");
            $stmt->execute([
                ':curso'=>$idCurso, ':nombre'=>$nombre, ':descripcion'=>$this->texto($datos['descripcion'] ?? ''),
                ':inicio'=>$inicio, ':limite'=>$limite, ':estado'=>$estado, ':creador'=>$idAdmin
            ]);
            $idCap = (int)$this->conexion->lastInsertId();
            $this->asignarUsuariosInterno($idCap, $usuarios);
            $this->conexion->commit();
            return $idCap;
        } catch (Throwable $e) {
            if ($this->conexion->inTransaction()) $this->conexion->rollBack();
            throw $e;
        }
    }

    private function asignarUsuariosInterno(int $idCapacitacion, array $usuarios): void
    {
        $capStmt = $this->conexion->prepare("SELECT cap.nombre,cap.fecha_limite,c.titulo AS curso FROM learning_capacitaciones cap INNER JOIN learning_cursos c ON c.id_curso=cap.id_curso WHERE cap.id_capacitacion=:id LIMIT 1");
        $capStmt->execute([':id'=>$idCapacitacion]);
        $capacitacion = $capStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $stmt = $this->conexion->prepare("INSERT IGNORE INTO learning_asignaciones (id_capacitacion,id_usuario) VALUES (:cap,:usuario)");
        foreach (array_unique(array_map('intval',$usuarios)) as $idUsuario) {
            if ($idUsuario <= 0) continue;
            $stmt->execute([':cap'=>$idCapacitacion, ':usuario'=>$idUsuario]);
            if ($stmt->rowCount() > 0) {
                $idAsignacion = (int)$this->conexion->lastInsertId();
                $fecha = !empty($capacitacion['fecha_limite']) ? date('d/m/Y', strtotime($capacitacion['fecha_limite'])) : '';
                $mensaje = 'Se te asignó ' . ($capacitacion['nombre'] ?? 'una nueva capacitación');
                if (!empty($capacitacion['curso'])) $mensaje .= ' del curso ' . $capacitacion['curso'];
                if ($fecha !== '') $mensaje .= '. Fecha límite: ' . $fecha;
                $mensaje .= '.';
                $this->notificaciones->crear(
                    $idUsuario,
                    'capacitacion_asignada',
                    'Nueva capacitación asignada',
                    $mensaje,
                    '/DEVIOZ-VIDEOS/public/curso.php?id_asignacion=' . $idAsignacion,
                    '🎓',
                    'cap_asignada:' . $idAsignacion
                );
            }
        }
    }

    public function asignarUsuarios(int $idCapacitacion, array $usuarios): void
    {
        $this->asignarUsuariosInterno($idCapacitacion, $usuarios);
    }

    public function actualizarCapacitacion(int $idCapacitacion, array $datos): bool
    {
        $inicio = (string)($datos['fecha_inicio'] ?? '');
        $limite = (string)($datos['fecha_limite'] ?? '');
        if (!$inicio || !$limite || $limite < $inicio) return false;
        $estado = in_array(($datos['estado'] ?? ''), ['planificada','activa','cerrada'], true) ? $datos['estado'] : 'activa';

        $anteriorStmt=$this->conexion->prepare("SELECT nombre,fecha_limite FROM learning_capacitaciones WHERE id_capacitacion=:id LIMIT 1");
        $anteriorStmt->execute([':id'=>$idCapacitacion]);
        $anterior=$anteriorStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $stmt = $this->conexion->prepare("UPDATE learning_capacitaciones SET nombre=:nombre, descripcion=:descripcion, fecha_inicio=:inicio, fecha_limite=:limite, estado=:estado WHERE id_capacitacion=:id");
        $ok=$stmt->execute([
            ':nombre'=>$this->texto($datos['nombre'] ?? '',180), ':descripcion'=>$this->texto($datos['descripcion'] ?? ''),
            ':inicio'=>$inicio, ':limite'=>$limite, ':estado'=>$estado, ':id'=>$idCapacitacion
        ]);

        if ($ok && !empty($anterior) && ($anterior['fecha_limite'] ?? '') !== $limite) {
            $usuarios=$this->conexion->prepare("SELECT id_asignacion,id_usuario FROM learning_asignaciones WHERE id_capacitacion=:cap AND estado IN ('pendiente','en_progreso')");
            $usuarios->execute([':cap'=>$idCapacitacion]);
            foreach ($usuarios->fetchAll(PDO::FETCH_ASSOC) as $a) {
                $this->notificaciones->crear(
                    (int)$a['id_usuario'],
                    'fecha_actualizada',
                    'Fecha límite actualizada',
                    'La fecha límite de ' . ($datos['nombre'] ?? $anterior['nombre'] ?? 'tu capacitación') . ' cambió al ' . date('d/m/Y',strtotime($limite)) . '.',
                    '/DEVIOZ-VIDEOS/public/curso.php?id_asignacion=' . (int)$a['id_asignacion'],
                    '📅',
                    'cap_fecha:' . (int)$a['id_asignacion'] . ':' . $limite
                );
            }
        }
        return $ok;
    }

    public function listarCapacitacionesAdmin(): array
    {
        $this->actualizarEstadosVencidos();
        $sql = "
            SELECT cap.*, c.titulo AS curso,
                   COUNT(a.id_asignacion) AS asignados,
                   SUM(a.estado='completada') AS completadas,
                   SUM(a.estado='vencida') AS vencidas
            FROM learning_capacitaciones cap
            INNER JOIN learning_cursos c ON c.id_curso=cap.id_curso
            LEFT JOIN learning_asignaciones a ON a.id_capacitacion=cap.id_capacitacion
            GROUP BY cap.id_capacitacion
            ORDER BY cap.fecha_creacion DESC
        ";
        return $this->conexion->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarCapacitacion(int $id): ?array
    {
        $stmt = $this->conexion->prepare("SELECT cap.*,c.titulo AS curso,c.nivel,c.duracion_estimada_minutos FROM learning_capacitaciones cap INNER JOIN learning_cursos c ON c.id_curso=cap.id_curso WHERE cap.id_capacitacion=:id LIMIT 1");
        $stmt->execute([':id'=>$id]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function asignacionesCapacitacion(int $idCapacitacion): array
    {
        $this->actualizarEstadosVencidos();
        $sql = "
            SELECT a.*,u.nombre,u.email,
                   COUNT(DISTINCT l.id_leccion) AS total_lecciones,
                   SUM(CASE WHEN pl.estado='completada' THEN 1 ELSE 0 END) AS completadas,
                   MAX(i.porcentaje) AS mejor_nota
            FROM learning_asignaciones a
            INNER JOIN usuarios u ON u.id_usuario=a.id_usuario
            INNER JOIN learning_capacitaciones cap ON cap.id_capacitacion=a.id_capacitacion
            LEFT JOIN learning_curso_lecciones l ON l.id_curso=cap.id_curso AND l.obligatoria=1
            LEFT JOIN learning_progreso_lecciones pl ON pl.id_asignacion=a.id_asignacion AND pl.id_leccion=l.id_leccion
            LEFT JOIN learning_intentos i ON i.id_asignacion=a.id_asignacion
            WHERE a.id_capacitacion=:cap
            GROUP BY a.id_asignacion
            ORDER BY u.nombre
        ";
        $stmt=$this->conexion->prepare($sql); $stmt->execute([':cap'=>$idCapacitacion]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function quitarAsignacion(int $idAsignacion, int $idCapacitacion): bool
    {
        $stmt=$this->conexion->prepare("DELETE FROM learning_asignaciones WHERE id_asignacion=:id AND id_capacitacion=:cap");
        return $stmt->execute([':id'=>$idAsignacion, ':cap'=>$idCapacitacion]);
    }

    public function eliminarCapacitacion(int $id): bool
    {
        $stmt=$this->conexion->prepare("DELETE FROM learning_capacitaciones WHERE id_capacitacion=:id");
        return $stmt->execute([':id'=>$id]);
    }

    public function actualizarEstadosVencidos(): void
    {
        $this->conexion->exec("UPDATE learning_asignaciones a INNER JOIN learning_capacitaciones cap ON cap.id_capacitacion=a.id_capacitacion SET a.estado='vencida', a.fecha_completado=NULL WHERE a.estado IN ('pendiente','en_progreso') AND cap.fecha_limite < CURDATE()");

        // Reconciliación de datos creados con la V1: una asignación que figure
        // completada no puede seguir así si no existe un examen publicado aprobado.
        $this->conexion->exec("UPDATE learning_asignaciones a INNER JOIN learning_capacitaciones cap ON cap.id_capacitacion=a.id_capacitacion LEFT JOIN learning_evaluaciones e ON e.id_curso=cap.id_curso AND e.estado='publicada' LEFT JOIN learning_intentos i ON i.id_asignacion=a.id_asignacion AND i.id_evaluacion=e.id_evaluacion AND i.aprobado=1 SET a.estado=CASE WHEN cap.fecha_limite < CURDATE() THEN 'vencida' ELSE 'en_progreso' END, a.fecha_completado=NULL WHERE a.estado='completada' AND (e.id_evaluacion IS NULL OR i.id_intento IS NULL)");
    }

    public function sincronizarProgresoAsignacion(int $idAsignacion, int $idUsuario): void
    {
        $detalle=$this->detalleAsignacionBase($idAsignacion,$idUsuario);
        if (!$detalle) return;

        // V3.2.1: el progreso academico vive en learning_progreso_lecciones.
        // Ya no se sincroniza desde video_progreso, porque ver un video fuera de una
        // capacitacion no debe completar automaticamente una leccion del curso.
        $stmt=$this->conexion->prepare("INSERT IGNORE INTO learning_progreso_lecciones (id_asignacion,id_leccion) SELECT :asig,l.id_leccion FROM learning_curso_lecciones l WHERE l.id_curso=:curso");
        $stmt->execute([':asig'=>$idAsignacion, ':curso'=>$detalle['id_curso']]);

        $sync=$this->conexion->prepare("UPDATE learning_progreso_lecciones SET estado=CASE WHEN porcentaje>=90 THEN 'completada' WHEN porcentaje>0 THEN 'en_progreso' ELSE 'pendiente' END, fecha_inicio=CASE WHEN porcentaje>0 AND fecha_inicio IS NULL THEN NOW() ELSE fecha_inicio END, fecha_completado=CASE WHEN porcentaje>=90 AND fecha_completado IS NULL THEN NOW() WHEN porcentaje<90 THEN NULL ELSE fecha_completado END WHERE id_asignacion=:asig");
        $sync->execute([':asig'=>$idAsignacion]);

        $this->actualizarEstadoAsignacion($idAsignacion,$idUsuario);
    }

    public function progresoLeccionAsignacion(int $idAsignacion, int $idVideo, int $idUsuario): ?array
    {
        $detalle=$this->detalleAsignacionBase($idAsignacion,$idUsuario);
        if (!$detalle) return null;

        $this->sincronizarProgresoAsignacion($idAsignacion,$idUsuario);
        $sql="SELECT pl.*,l.id_video,l.obligatoria,l.orden FROM learning_progreso_lecciones pl INNER JOIN learning_curso_lecciones l ON l.id_leccion=pl.id_leccion WHERE pl.id_asignacion=:asig AND l.id_curso=:curso AND l.id_video=:video LIMIT 1";
        $stmt=$this->conexion->prepare($sql);
        $stmt->execute([':asig'=>$idAsignacion, ':curso'=>$detalle['id_curso'], ':video'=>$idVideo]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function guardarProgresoLeccion(int $idAsignacion, int $idVideo, int $idUsuario, int $posicion, int $duracion, bool $finalizado=false): array
    {
        $detalle=$this->detalleAsignacionBase($idAsignacion,$idUsuario);
        if (!$detalle) return ['ok'=>false,'mensaje'=>'Asignacion no valida.'];
        if ($detalle['fecha_inicio'] > date('Y-m-d')) return ['ok'=>false,'mensaje'=>'La capacitacion aun no ha iniciado.'];
        if ($detalle['estado']==='vencida' || $detalle['fecha_limite'] < date('Y-m-d')) return ['ok'=>false,'mensaje'=>'La capacitacion esta vencida.'];

        $progreso=$this->progresoLeccionAsignacion($idAsignacion,$idVideo,$idUsuario);
        if (!$progreso) return ['ok'=>false,'mensaje'=>'El video no pertenece a esta capacitacion.'];

        // V3.2.1 hotfix: en cursos secuenciales no se acepta progreso de una
        // leccion futura mientras exista una leccion obligatoria anterior pendiente.
        if (!empty($detalle['orden_secuencial'])) {
            $bloqueo=$this->conexion->prepare("SELECT COUNT(*) FROM learning_curso_lecciones anterior LEFT JOIN learning_progreso_lecciones pa ON pa.id_leccion=anterior.id_leccion AND pa.id_asignacion=:asig WHERE anterior.id_curso=:curso AND anterior.obligatoria=1 AND (anterior.orden < :orden OR (anterior.orden = :orden2 AND anterior.id_leccion < :leccion)) AND COALESCE(pa.estado,'pendiente') <> 'completada'");
            $bloqueo->execute([
                ':asig'=>$idAsignacion,
                ':curso'=>$detalle['id_curso'],
                ':orden'=>(int)$progreso['orden'],
                ':orden2'=>(int)$progreso['orden'],
                ':leccion'=>(int)$progreso['id_leccion'],
            ]);
            if ((int)$bloqueo->fetchColumn() > 0) {
                return ['ok'=>false,'mensaje'=>'Completa la leccion anterior antes de continuar.'];
            }
        }

        $posicion=max(0,$posicion);
        $duracion=max(0,$duracion);
        $maxAnterior=(int)($progreso['max_posicion_segundos'] ?? 0);
        $completada=($progreso['estado'] ?? '')==='completada';

        // Defensa de servidor frente a saltos artificiales. El navegador bloquea el
        // seek; aqui limitamos ademas avances bruscos enviados directamente al API.
        if (!$completada && $posicion > ($maxAnterior + 45)) {
            $posicion=$maxAnterior;
        }

        $nuevoMax=max($maxAnterior,$posicion);
        if ($finalizado && $duracion>0 && ($duracion-$nuevoMax)<=20) {
            $nuevoMax=$duracion;
            $posicion=$duracion;
        }

        $porcentaje=$duracion>0 ? min(100,round(($nuevoMax/$duracion)*100,2)) : (float)($progreso['porcentaje'] ?? 0);
        if ($completada) $porcentaje=max(90,$porcentaje);
        $estado=$porcentaje>=90 ? 'completada' : ($porcentaje>0 ? 'en_progreso' : 'pendiente');

        $sql="UPDATE learning_progreso_lecciones SET posicion_segundos=:posicion,duracion_segundos=:duracion,max_posicion_segundos=:maximo,porcentaje=:porcentaje,estado=:estado,fecha_inicio=CASE WHEN :porcentaje_inicio>0 AND fecha_inicio IS NULL THEN NOW() ELSE fecha_inicio END,fecha_completado=CASE WHEN :porcentaje_fin>=90 THEN COALESCE(fecha_completado,NOW()) ELSE NULL END WHERE id_progreso_leccion=:id";
        $stmt=$this->conexion->prepare($sql);
        $ok=$stmt->execute([
            ':posicion'=>$posicion,
            ':duracion'=>$duracion,
            ':maximo'=>$nuevoMax,
            ':porcentaje'=>$porcentaje,
            ':estado'=>$estado,
            ':porcentaje_inicio'=>$porcentaje,
            ':porcentaje_fin'=>$porcentaje,
            ':id'=>$progreso['id_progreso_leccion'],
        ]);

        if ($ok) $this->actualizarEstadoAsignacion($idAsignacion,$idUsuario);
        return [
            'ok'=>$ok,
            'porcentaje'=>$porcentaje,
            'posicion'=>$posicion,
            'max_posicion'=>$nuevoMax,
            'estado'=>$estado,
            'completada'=>$estado==='completada',
        ];
    }

    private function actualizarEstadoAsignacion(int $idAsignacion, int $idUsuario): void
    {
        $detalle=$this->detalleAsignacionBase($idAsignacion,$idUsuario);
        if (!$detalle) return;
        $stmt=$this->conexion->prepare("SELECT COUNT(*) total, SUM(pl.estado='completada') completas, SUM(pl.estado='en_progreso') en_progreso FROM learning_curso_lecciones l LEFT JOIN learning_progreso_lecciones pl ON pl.id_leccion=l.id_leccion AND pl.id_asignacion=:asig WHERE l.id_curso=:curso AND l.obligatoria=1");
        $stmt->execute([':asig'=>$idAsignacion, ':curso'=>$detalle['id_curso']]);
        $p=$stmt->fetch(PDO::FETCH_ASSOC) ?: ['total'=>0,'completas'=>0,'en_progreso'=>0];
        $total=(int)$p['total'];
        $completas=(int)$p['completas'];
        $enProgreso=(int)$p['en_progreso'];
        $todasLecciones=($total===0 || $completas >= $total);

        // Regla Learning V2: una capacitación solo se completa cuando existe
        // una evaluación FINAL PUBLICADA y el usuario la ha APROBADO.
        // Esto evita entregar finalización/logros por ver solamente los videos.
        $eval=$this->evaluacionCurso((int)$detalle['id_curso'], true);
        $aprobado=false;
        if ($eval) {
            $q=$this->conexion->prepare("SELECT 1 FROM learning_intentos WHERE id_asignacion=:a AND id_evaluacion=:e AND aprobado=1 LIMIT 1");
            $q->execute([':a'=>$idAsignacion, ':e'=>$eval['id_evaluacion']]);
            $aprobado=(bool)$q->fetchColumn();
        }

        if ($todasLecciones && $eval && $aprobado) {
            $u=$this->conexion->prepare("UPDATE learning_asignaciones SET estado='completada', fecha_inicio_real=COALESCE(fecha_inicio_real,NOW()), fecha_completado=COALESCE(fecha_completado,NOW()) WHERE id_asignacion=:id");
            $u->execute([':id'=>$idAsignacion]);
            $this->notificaciones->crear(
                $idUsuario,
                'capacitacion_completada',
                'Capacitación completada',
                'Completaste ' . ($detalle['capacitacion'] ?? 'la capacitación') . ' y aprobaste su evaluación.',
                '/DEVIOZ-VIDEOS/public/curso.php?id_asignacion=' . $idAsignacion,
                '✅',
                'cap_completada:' . $idAsignacion
            );
            $this->evaluarLogrosUsuario($idUsuario,$idAsignacion);
            return;
        }

        if ($todasLecciones && $eval && !$aprobado) {
            $intentosStmt=$this->conexion->prepare("SELECT COUNT(*) FROM learning_intentos WHERE id_asignacion=:a AND id_evaluacion=:e");
            $intentosStmt->execute([':a'=>$idAsignacion,':e'=>$eval['id_evaluacion']]);
            $intentosUsados=(int)$intentosStmt->fetchColumn();
            if ($intentosUsados < (int)$eval['intentos_permitidos']) {
                $this->notificaciones->crear(
                    $idUsuario,
                    'evaluacion_disponible',
                    'Evaluación disponible',
                    'Ya completaste las lecciones de ' . ($detalle['capacitacion'] ?? 'tu capacitación') . '. Puedes rendir la evaluación final.',
                    '/DEVIOZ-VIDEOS/public/evaluacion.php?id_asignacion=' . $idAsignacion,
                    '📝',
                    'eval_disponible:' . $idAsignacion . ':' . (int)$eval['id_evaluacion']
                );
            }
        }

        // Si la fecha venció sin cumplir videos + examen, la asignación queda vencida,
        // incluso si una versión anterior la había marcado como completada por error.
        if ($detalle['fecha_limite'] < date('Y-m-d')) {
            $u=$this->conexion->prepare("UPDATE learning_asignaciones SET estado='vencida', fecha_completado=NULL WHERE id_asignacion=:id");
            $u->execute([':id'=>$idAsignacion]);
            $this->evaluarLogrosUsuario($idUsuario,null);
            return;
        }

        // Si antes se marcó como completada y luego se publicó un examen,
        // reconciliamos el estado para exigir la aprobación del examen.
        if ($todasLecciones || $completas>0 || $enProgreso>0) {
            $u=$this->conexion->prepare("UPDATE learning_asignaciones SET estado='en_progreso', fecha_inicio_real=COALESCE(fecha_inicio_real,NOW()), fecha_completado=NULL WHERE id_asignacion=:id");
            $u->execute([':id'=>$idAsignacion]);
        } else {
            $u=$this->conexion->prepare("UPDATE learning_asignaciones SET estado='pendiente', fecha_completado=NULL WHERE id_asignacion=:id AND estado<>'vencida'");
            $u->execute([':id'=>$idAsignacion]);
        }
        $this->evaluarLogrosUsuario($idUsuario,null);
    }

    private function detalleAsignacionBase(int $idAsignacion, int $idUsuario): ?array
    {
        $stmt=$this->conexion->prepare("SELECT a.*,cap.id_curso,cap.nombre AS capacitacion,cap.descripcion AS capacitacion_descripcion,cap.fecha_inicio,cap.fecha_limite,cap.estado AS capacitacion_estado,c.titulo AS curso,c.descripcion AS curso_descripcion,c.nivel,c.duracion_estimada_minutos,c.orden_secuencial FROM learning_asignaciones a INNER JOIN learning_capacitaciones cap ON cap.id_capacitacion=a.id_capacitacion INNER JOIN learning_cursos c ON c.id_curso=cap.id_curso WHERE a.id_asignacion=:id AND a.id_usuario=:usuario LIMIT 1");
        $stmt->execute([':id'=>$idAsignacion, ':usuario'=>$idUsuario]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function detalleAsignacion(int $idAsignacion, int $idUsuario): ?array
    {
        $this->sincronizarProgresoAsignacion($idAsignacion,$idUsuario);
        $detalle=$this->detalleAsignacionBase($idAsignacion,$idUsuario);
        if (!$detalle) return null;
        $detalle['progreso']=$this->progresoAsignacion($idAsignacion,(int)$detalle['id_curso']);
        $detalle['evaluacion']=$this->evaluacionCurso((int)$detalle['id_curso'], true);
        return $detalle;
    }

    public function progresoAsignacion(int $idAsignacion, int $idCurso): array
    {
        $stmt=$this->conexion->prepare("SELECT COUNT(*) total, SUM(CASE WHEN pl.estado='completada' THEN 1 ELSE 0 END) completadas, SUM(CASE WHEN pl.estado='en_progreso' THEN 1 ELSE 0 END) en_progreso FROM learning_curso_lecciones l LEFT JOIN learning_progreso_lecciones pl ON pl.id_leccion=l.id_leccion AND pl.id_asignacion=:asig WHERE l.id_curso=:curso AND l.obligatoria=1");
        $stmt->execute([':asig'=>$idAsignacion, ':curso'=>$idCurso]);
        $r=$stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $total=(int)($r['total']??0); $completadas=(int)($r['completadas']??0);
        return ['total'=>$total,'completadas'=>$completadas,'en_progreso'=>(int)($r['en_progreso']??0),'porcentaje'=>$total>0?round(($completadas/$total)*100,1):0];
    }

    public function leccionesAsignacion(int $idAsignacion, int $idUsuario): array
    {
        $this->sincronizarProgresoAsignacion($idAsignacion,$idUsuario);
        $detalle=$this->detalleAsignacionBase($idAsignacion,$idUsuario);
        if (!$detalle) return [];
        $sql="SELECT l.id_leccion,l.id_video,l.orden,l.obligatoria,COALESCE(NULLIF(l.titulo_personalizado,''),v.titulo) titulo,l.descripcion,v.miniatura,v.titulo titulo_video,COALESCE(pl.estado,'pendiente') progreso_estado,COALESCE(pl.porcentaje,0) porcentaje,COALESCE(pl.posicion_segundos,0) posicion_segundos,COALESCE(pl.duracion_segundos,0) duracion_segundos,COALESCE(pl.max_posicion_segundos,0) max_posicion_segundos,pl.fecha_completado FROM learning_curso_lecciones l INNER JOIN videos v ON v.id_video=l.id_video LEFT JOIN learning_progreso_lecciones pl ON pl.id_leccion=l.id_leccion AND pl.id_asignacion=:asig WHERE l.id_curso=:curso ORDER BY l.orden,l.id_leccion";
        $stmt=$this->conexion->prepare($sql); $stmt->execute([':asig'=>$idAsignacion, ':curso'=>$detalle['id_curso']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function marcarLeccionCompleta(int $idAsignacion, int $idLeccion, int $idUsuario): bool
    {
        // Compatibilidad defensiva: ya no se permite completar una lección
        // mediante un botón/manual POST. Solo se valida el progreso REAL del video.
        $detalle=$this->detalleAsignacionBase($idAsignacion,$idUsuario);
        if (!$detalle || $detalle['estado'] === 'vencida' || $detalle['fecha_inicio'] > date('Y-m-d')) return false;

        $stmt=$this->conexion->prepare("SELECT COALESCE(pl.porcentaje,0) porcentaje FROM learning_curso_lecciones l LEFT JOIN learning_progreso_lecciones pl ON pl.id_leccion=l.id_leccion AND pl.id_asignacion=:asig WHERE l.id_leccion=:leccion AND l.id_curso=:curso LIMIT 1");
        $stmt->execute([':asig'=>$idAsignacion, ':leccion'=>$idLeccion, ':curso'=>$detalle['id_curso']]);
        $porcentaje=(float)($stmt->fetchColumn() ?: 0);
        if ($porcentaje < 90) return false;

        $this->sincronizarProgresoAsignacion($idAsignacion,$idUsuario);
        $check=$this->conexion->prepare("SELECT estado FROM learning_progreso_lecciones WHERE id_asignacion=:asig AND id_leccion=:leccion LIMIT 1");
        $check->execute([':asig'=>$idAsignacion, ':leccion'=>$idLeccion]);
        return $check->fetchColumn()==='completada';
    }

    public function misAsignaciones(int $idUsuario): array
    {
        $this->actualizarEstadosVencidos();
        $ids=$this->conexion->prepare("SELECT id_asignacion FROM learning_asignaciones WHERE id_usuario=:u");
        $ids->execute([':u'=>$idUsuario]);
        foreach ($ids->fetchAll(PDO::FETCH_COLUMN) as $id) $this->sincronizarProgresoAsignacion((int)$id,$idUsuario);
        $sql="SELECT a.*,cap.nombre AS capacitacion,cap.descripcion,cap.fecha_inicio,cap.fecha_limite,cap.estado AS capacitacion_estado,c.id_curso,c.titulo AS curso,c.nivel,c.duracion_estimada_minutos,COUNT(DISTINCT l.id_leccion) total_lecciones,SUM(CASE WHEN pl.estado='completada' THEN 1 ELSE 0 END) completadas,MIN(v.miniatura) miniatura_referencia FROM learning_asignaciones a INNER JOIN learning_capacitaciones cap ON cap.id_capacitacion=a.id_capacitacion INNER JOIN learning_cursos c ON c.id_curso=cap.id_curso LEFT JOIN learning_curso_lecciones l ON l.id_curso=c.id_curso AND l.obligatoria=1 LEFT JOIN learning_progreso_lecciones pl ON pl.id_asignacion=a.id_asignacion AND pl.id_leccion=l.id_leccion LEFT JOIN videos v ON v.id_video=l.id_video WHERE a.id_usuario=:u GROUP BY a.id_asignacion ORDER BY FIELD(a.estado,'en_progreso','pendiente','vencida','completada'),cap.fecha_limite ASC";
        $stmt=$this->conexion->prepare($sql); $stmt->execute([':u'=>$idUsuario]);
        $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $total=(int)$r['total_lecciones']; $comp=(int)$r['completadas'];
            $r['porcentaje']=$total>0?round(($comp/$total)*100,1):0;
            $hoy=new DateTimeImmutable('today'); $limite=new DateTimeImmutable($r['fecha_limite']);
            $r['dias_restantes']=(int)$hoy->diff($limite)->format('%r%a');
        }
        unset($r);
        return $rows;
    }

    public function catalogoCursosParaUsuario(int $idUsuario): array
    {
        $sql="SELECT c.*,COUNT(DISTINCT l.id_leccion) total_lecciones,MIN(v.miniatura) miniatura_referencia,EXISTS(SELECT 1 FROM learning_asignaciones a INNER JOIN learning_capacitaciones cap ON cap.id_capacitacion=a.id_capacitacion WHERE a.id_usuario=:u AND cap.id_curso=c.id_curso) AS asignado FROM learning_cursos c LEFT JOIN learning_curso_lecciones l ON l.id_curso=c.id_curso LEFT JOIN videos v ON v.id_video=l.id_video WHERE c.estado='publicado' GROUP BY c.id_curso ORDER BY c.fecha_actualizacion DESC";
        $stmt=$this->conexion->prepare($sql); $stmt->execute([':u'=>$idUsuario]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function evaluacionCurso(int $idCurso, bool $soloPublicada=false): ?array
    {
        $sql="SELECT e.*,COUNT(p.id_pregunta) total_preguntas,COALESCE(SUM(p.puntos),0) puntos_totales FROM learning_evaluaciones e LEFT JOIN learning_preguntas p ON p.id_evaluacion=e.id_evaluacion WHERE e.id_curso=:curso";
        if ($soloPublicada) $sql .= " AND e.estado='publicada'";
        $sql .= " GROUP BY e.id_evaluacion LIMIT 1";
        $stmt=$this->conexion->prepare($sql); $stmt->execute([':curso'=>$idCurso]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC); return $row ?: null;
    }

    public function listarEvaluacionesAdmin(): array
    {
        $sql="SELECT c.id_curso,c.titulo AS curso,c.estado AS curso_estado,e.id_evaluacion,e.titulo,e.nota_minima,e.intentos_permitidos,e.estado,COUNT(p.id_pregunta) total_preguntas FROM learning_cursos c LEFT JOIN learning_evaluaciones e ON e.id_curso=c.id_curso LEFT JOIN learning_preguntas p ON p.id_evaluacion=e.id_evaluacion GROUP BY c.id_curso,e.id_evaluacion ORDER BY c.titulo";
        return $this->conexion->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function guardarEvaluacion(int $idCurso, array $datos): int
    {
        $titulo=$this->texto($datos['titulo'] ?? '',180);
        if ($titulo==='') throw new InvalidArgumentException('El título de la evaluación es obligatorio.');
        $nota=max(0,min(100,(float)($datos['nota_minima']??70)));
        $intentos=max(1,min(20,(int)($datos['intentos_permitidos']??3)));
        $estado=in_array(($datos['estado']??''),['borrador','publicada'],true)?$datos['estado']:'borrador';
        $actual=$this->evaluacionCurso($idCurso,false);
        if ($actual) {
            $stmt=$this->conexion->prepare("UPDATE learning_evaluaciones SET titulo=:titulo,descripcion=:descripcion,nota_minima=:nota,intentos_permitidos=:intentos,estado=:estado WHERE id_evaluacion=:id");
            $stmt->execute([':titulo'=>$titulo,':descripcion'=>$this->texto($datos['descripcion']??''),':nota'=>$nota,':intentos'=>$intentos,':estado'=>$estado,':id'=>$actual['id_evaluacion']]);
            return (int)$actual['id_evaluacion'];
        }
        $stmt=$this->conexion->prepare("INSERT INTO learning_evaluaciones (id_curso,titulo,descripcion,nota_minima,intentos_permitidos,estado) VALUES (:curso,:titulo,:descripcion,:nota,:intentos,:estado)");
        $stmt->execute([':curso'=>$idCurso,':titulo'=>$titulo,':descripcion'=>$this->texto($datos['descripcion']??''),':nota'=>$nota,':intentos'=>$intentos,':estado'=>$estado]);
        return (int)$this->conexion->lastInsertId();
    }

    public function preguntasEvaluacion(int $idEvaluacion): array
    {
        $stmt=$this->conexion->prepare("SELECT * FROM learning_preguntas WHERE id_evaluacion=:e ORDER BY orden,id_pregunta");
        $stmt->execute([':e'=>$idEvaluacion]); $preguntas=$stmt->fetchAll(PDO::FETCH_ASSOC);
        $op=$this->conexion->prepare("SELECT * FROM learning_opciones WHERE id_pregunta=:p ORDER BY orden,id_opcion");
        foreach ($preguntas as &$p) { $op->execute([':p'=>$p['id_pregunta']]); $p['opciones']=$op->fetchAll(PDO::FETCH_ASSOC); }
        unset($p); return $preguntas;
    }

    public function agregarPregunta(int $idEvaluacion, array $datos): int
    {
        $pregunta=$this->texto($datos['pregunta']??'',800);
        if ($pregunta==='') throw new InvalidArgumentException('Escribe la pregunta.');
        $tipo=in_array(($datos['tipo']??''),['opcion_multiple','verdadero_falso'],true)?$datos['tipo']:'opcion_multiple';
        $puntos=max(.1,(float)($datos['puntos']??1));
        $orden=max(1,(int)($datos['orden']??1));
        $opciones=[]; $correcta=0;
        if ($tipo==='verdadero_falso') {
            $opciones=['Verdadero','Falso'];
            $correcta=(($datos['respuesta_vf']??'verdadero')==='falso')?1:0;
        } else {
            for ($i=1;$i<=4;$i++) {
                $txt=$this->texto($datos['opcion_'.$i]??'',500);
                if ($txt!=='') $opciones[]=$txt;
            }
            $correcta=max(0,(int)($datos['correcta']??1)-1);
            if (count($opciones)<2 || !isset($opciones[$correcta])) throw new InvalidArgumentException('Agrega al menos dos opciones y marca una respuesta correcta válida.');
        }
        $this->conexion->beginTransaction();
        try {
            $stmt=$this->conexion->prepare("INSERT INTO learning_preguntas (id_evaluacion,pregunta,tipo,puntos,orden) VALUES (:e,:p,:t,:pts,:o)");
            $stmt->execute([':e'=>$idEvaluacion,':p'=>$pregunta,':t'=>$tipo,':pts'=>$puntos,':o'=>$orden]);
            $id=(int)$this->conexion->lastInsertId();
            $ins=$this->conexion->prepare("INSERT INTO learning_opciones (id_pregunta,texto,es_correcta,orden) VALUES (:p,:t,:c,:o)");
            foreach ($opciones as $idx=>$txt) $ins->execute([':p'=>$id,':t'=>$txt,':c'=>$idx===$correcta?1:0,':o'=>$idx+1]);
            $this->conexion->commit(); return $id;
        } catch (Throwable $e) { if ($this->conexion->inTransaction()) $this->conexion->rollBack(); throw $e; }
    }

    public function eliminarPregunta(int $idPregunta, int $idEvaluacion): bool
    {
        $stmt=$this->conexion->prepare("DELETE FROM learning_preguntas WHERE id_pregunta=:id AND id_evaluacion=:e");
        return $stmt->execute([':id'=>$idPregunta,':e'=>$idEvaluacion]);
    }

    public function intentosAsignacion(int $idAsignacion, int $idEvaluacion): array
    {
        $stmt=$this->conexion->prepare("SELECT * FROM learning_intentos WHERE id_asignacion=:a AND id_evaluacion=:e ORDER BY numero_intento DESC");
        $stmt->execute([':a'=>$idAsignacion,':e'=>$idEvaluacion]); return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Devuelve la revisión del último intento únicamente cuando el usuario
     * agotó todos sus intentos sin aprobar. Las respuestas correctas no se
     * exponen mientras exista un intento disponible.
     */
    public function revisionEvaluacionAgotada(int $idAsignacion, int $idUsuario): ?array
    {
        $detalle=$this->detalleAsignacion($idAsignacion,$idUsuario);
        if (!$detalle || !$detalle['evaluacion']) return null;

        $eval=$detalle['evaluacion'];
        $intentos=$this->intentosAsignacion($idAsignacion,(int)$eval['id_evaluacion']);
        if (count($intentos)<(int)$eval['intentos_permitidos']) return null;
        foreach ($intentos as $intento) {
            if (!empty($intento['aprobado'])) return null;
        }
        if (!$intentos) return null;

        $ultimo=$intentos[0];
        $stmt=$this->conexion->prepare("SELECT p.id_pregunta,p.pregunta,p.tipo,p.puntos,
                r.id_opcion AS id_opcion_usuario,r.es_correcta,r.puntos_obtenidos,
                ou.texto AS respuesta_usuario,
                oc.id_opcion AS id_opcion_correcta,oc.texto AS respuesta_correcta
            FROM learning_preguntas p
            LEFT JOIN learning_respuestas r ON r.id_pregunta=p.id_pregunta AND r.id_intento=:intento
            LEFT JOIN learning_opciones ou ON ou.id_opcion=r.id_opcion
            LEFT JOIN learning_opciones oc ON oc.id_pregunta=p.id_pregunta AND oc.es_correcta=1
            WHERE p.id_evaluacion=:evaluacion
            ORDER BY p.orden,p.id_pregunta");
        $stmt->execute([':intento'=>$ultimo['id_intento'],':evaluacion'=>$eval['id_evaluacion']]);
        $respuestas=$stmt->fetchAll(PDO::FETCH_ASSOC);

        $correctas=0;
        foreach ($respuestas as $r) if (!empty($r['es_correcta'])) $correctas++;

        return [
            'intento'=>$ultimo,
            'evaluacion'=>$eval,
            'respuestas'=>$respuestas,
            'correctas'=>$correctas,
            'total'=>count($respuestas),
        ];
    }

    public function puedeRendirEvaluacion(int $idAsignacion, int $idUsuario): array
    {
        $detalle=$this->detalleAsignacion($idAsignacion,$idUsuario);
        if (!$detalle || !$detalle['evaluacion']) return ['ok'=>false,'motivo'=>'No hay evaluación publicada.'];
        if ($detalle['fecha_inicio'] > date('Y-m-d')) return ['ok'=>false,'motivo'=>'La capacitación todavía no ha iniciado.'];
        if ($detalle['estado'] === 'vencida') return ['ok'=>false,'motivo'=>'La fecha límite de esta capacitación ya venció.'];
        $p=$detalle['progreso'];
        if ($p['total']>0 && $p['completadas']<$p['total']) return ['ok'=>false,'motivo'=>'Completa todas las lecciones obligatorias antes del examen.'];
        $eval=$detalle['evaluacion'];
        $intentos=$this->intentosAsignacion($idAsignacion,(int)$eval['id_evaluacion']);
        foreach ($intentos as $i) if (!empty($i['aprobado'])) return ['ok'=>false,'motivo'=>'Ya aprobaste esta evaluación.','aprobado'=>true];
        if (count($intentos)>=(int)$eval['intentos_permitidos']) return ['ok'=>false,'motivo'=>'Ya utilizaste todos los intentos permitidos.'];
        if ((int)$eval['total_preguntas']===0) return ['ok'=>false,'motivo'=>'La evaluación todavía no tiene preguntas.'];
        return ['ok'=>true,'detalle'=>$detalle,'intentos'=>$intentos];
    }

    public function registrarIntento(int $idAsignacion, int $idUsuario, array $respuestas): array
    {
        $permiso=$this->puedeRendirEvaluacion($idAsignacion,$idUsuario);
        if (empty($permiso['ok'])) throw new RuntimeException($permiso['motivo']??'No puedes rendir esta evaluación.');
        $detalle=$permiso['detalle']; $eval=$detalle['evaluacion']; $preguntas=$this->preguntasEvaluacion((int)$eval['id_evaluacion']);
        $numero=count($permiso['intentos'])+1; $total=0.0; $obtenido=0.0; $procesadas=[];
        foreach ($preguntas as $p) {
            $pts=(float)$p['puntos']; $total+=$pts; $seleccion=(int)($respuestas[(int)$p['id_pregunta']]??0); $correcta=false; $idOpcion=null;
            foreach ($p['opciones'] as $o) {
                if ((int)$o['id_opcion']===$seleccion) { $idOpcion=$seleccion; $correcta=!empty($o['es_correcta']); break; }
            }
            $ganado=$correcta?$pts:0.0; $obtenido+=$ganado;
            $procesadas[]=['pregunta'=>(int)$p['id_pregunta'],'opcion'=>$idOpcion,'correcta'=>$correcta?1:0,'puntos'=>$ganado];
        }
        $porcentaje=$total>0?round(($obtenido/$total)*100,2):0; $aprobado=$porcentaje>=(float)$eval['nota_minima'];
        $this->conexion->beginTransaction();
        try {
            $stmt=$this->conexion->prepare("INSERT INTO learning_intentos (id_asignacion,id_evaluacion,numero_intento,puntos_obtenidos,puntos_totales,porcentaje,aprobado,fecha_fin) VALUES (:a,:e,:n,:po,:pt,:por,:ap,NOW())");
            $stmt->execute([':a'=>$idAsignacion,':e'=>$eval['id_evaluacion'],':n'=>$numero,':po'=>$obtenido,':pt'=>$total,':por'=>$porcentaje,':ap'=>$aprobado?1:0]);
            $idIntento=(int)$this->conexion->lastInsertId();
            $ins=$this->conexion->prepare("INSERT INTO learning_respuestas (id_intento,id_pregunta,id_opcion,es_correcta,puntos_obtenidos) VALUES (:i,:p,:o,:c,:pts)");
            foreach ($procesadas as $r) $ins->execute([':i'=>$idIntento,':p'=>$r['pregunta'],':o'=>$r['opcion']?:null,':c'=>$r['correcta'],':pts'=>$r['puntos']]);
            if ($aprobado) {
                $u=$this->conexion->prepare("UPDATE learning_asignaciones SET estado='completada',fecha_inicio_real=COALESCE(fecha_inicio_real,NOW()),fecha_completado=COALESCE(fecha_completado,NOW()) WHERE id_asignacion=:id AND id_usuario=:u");
                $u->execute([':id'=>$idAsignacion,':u'=>$idUsuario]);
            }
            $this->conexion->commit();
        } catch (Throwable $e) { if ($this->conexion->inTransaction()) $this->conexion->rollBack(); throw $e; }
        if ($aprobado) {
            $this->notificaciones->crear(
                $idUsuario,
                'capacitacion_completada',
                'Capacitación completada',
                'Aprobaste la evaluación de ' . ($detalle['capacitacion'] ?? $detalle['curso'] ?? 'tu capacitación') . ' con ' . round($porcentaje,1) . '%.',
                '/DEVIOZ-VIDEOS/public/curso.php?id_asignacion=' . $idAsignacion,
                '✅',
                'cap_completada:' . $idAsignacion
            );
        } elseif ($numero >= (int)$eval['intentos_permitidos']) {
            $this->notificaciones->crear(
                $idUsuario,
                'evaluacion_agotada',
                'Intentos de evaluación agotados',
                'Agotaste los intentos de ' . ($detalle['capacitacion'] ?? $detalle['curso'] ?? 'la capacitación') . '. Ya puedes revisar tus respuestas.',
                '/DEVIOZ-VIDEOS/public/evaluacion.php?id_asignacion=' . $idAsignacion,
                '📋',
                'eval_agotada:' . $idAsignacion . ':' . (int)$eval['id_evaluacion']
            );
        }
        $nuevos=$this->evaluarLogrosUsuario($idUsuario,$idAsignacion);
        return ['porcentaje'=>$porcentaje,'aprobado'=>$aprobado,'numero_intento'=>$numero,'nota_minima'=>(float)$eval['nota_minima'],'nuevos_logros'=>$nuevos];
    }

    public function listarLogrosAdmin(): array
    {
        $sql="SELECT l.*,c.titulo AS curso,COUNT(ul.id_usuario_logro) AS obtenidos FROM learning_logros l LEFT JOIN learning_cursos c ON c.id_curso=l.id_curso LEFT JOIN learning_usuario_logros ul ON ul.id_logro=l.id_logro GROUP BY l.id_logro ORDER BY l.estado DESC,l.fecha_creacion";
        return $this->conexion->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function guardarLogro(array $datos): int
    {
        $id=(int)($datos['id_logro']??0); $nombre=$this->texto($datos['nombre']??'',120); $codigo=strtoupper(preg_replace('/[^A-Za-z0-9_]+/','_',trim((string)($datos['codigo']??$nombre))));
        if ($nombre===''||$codigo==='') throw new InvalidArgumentException('Nombre y código son obligatorios.');
        $criterio=in_array(($datos['criterio']??''),['primer_curso','cursos_completados','nota_perfecta','curso_especifico'],true)?$datos['criterio']:'cursos_completados';
        $idCurso = null;
        if ($criterio === 'curso_especifico') {
            $idCurso = (int)($datos['id_curso'] ?? 0);
            if ($idCurso <= 0 || !$this->buscarCurso($idCurso)) {
                throw new InvalidArgumentException('Selecciona un curso válido para este logro.');
            }
        }
        $params=[':codigo'=>$codigo,':nombre'=>$nombre,':descripcion'=>$this->texto($datos['descripcion']??'',500),':icono'=>$this->texto($datos['icono']??'🏅',20)?:'🏅',':criterio'=>$criterio,':valor'=>max(1,(int)($datos['valor_objetivo']??1)),':curso'=>$idCurso,':estado'=>!empty($datos['estado'])?1:0];
        if ($id>0) { $params[':id']=$id; $stmt=$this->conexion->prepare("UPDATE learning_logros SET codigo=:codigo,nombre=:nombre,descripcion=:descripcion,icono=:icono,criterio=:criterio,valor_objetivo=:valor,id_curso=:curso,estado=:estado WHERE id_logro=:id"); $stmt->execute($params); return $id; }
        $stmt=$this->conexion->prepare("INSERT INTO learning_logros (codigo,nombre,descripcion,icono,criterio,valor_objetivo,id_curso,estado) VALUES (:codigo,:nombre,:descripcion,:icono,:criterio,:valor,:curso,:estado)"); $stmt->execute($params); return (int)$this->conexion->lastInsertId();
    }

    public function buscarLogro(int $id): ?array
    {
        $stmt=$this->conexion->prepare("SELECT * FROM learning_logros WHERE id_logro=:id"); $stmt->execute([':id'=>$id]); $r=$stmt->fetch(PDO::FETCH_ASSOC); return $r?:null;
    }

    public function evaluarLogrosUsuario(int $idUsuario, ?int $idAsignacion=null): array
    {
        $stmt=$this->conexion->prepare("SELECT COUNT(*) FROM learning_asignaciones WHERE id_usuario=:u AND estado='completada'"); $stmt->execute([':u'=>$idUsuario]); $completados=(int)$stmt->fetchColumn();
        $stmt=$this->conexion->prepare("SELECT COALESCE(MAX(porcentaje),0) FROM learning_intentos i INNER JOIN learning_asignaciones a ON a.id_asignacion=i.id_asignacion WHERE a.id_usuario=:u"); $stmt->execute([':u'=>$idUsuario]); $mejor=(float)$stmt->fetchColumn();
        $logros=$this->conexion->query("SELECT * FROM learning_logros WHERE estado=1 ORDER BY id_logro")->fetchAll(PDO::FETCH_ASSOC); $nuevos=[];
        $ins=$this->conexion->prepare("INSERT IGNORE INTO learning_usuario_logros (id_usuario,id_logro,id_asignacion) VALUES (:u,:l,:a)");
        $del=$this->conexion->prepare("DELETE FROM learning_usuario_logros WHERE id_usuario=:u AND id_logro=:l");
        foreach ($logros as $l) {
            $cumple=false;
            if ($l['criterio']==='primer_curso') $cumple=$completados>=1;
            elseif ($l['criterio']==='cursos_completados') $cumple=$completados>=(int)$l['valor_objetivo'];
            elseif ($l['criterio']==='nota_perfecta') $cumple=$mejor>=100;
            elseif ($l['criterio']==='curso_especifico' && $l['id_curso']) {
                $q=$this->conexion->prepare("SELECT 1 FROM learning_asignaciones a INNER JOIN learning_capacitaciones cap ON cap.id_capacitacion=a.id_capacitacion WHERE a.id_usuario=:u AND a.estado='completada' AND cap.id_curso=:c LIMIT 1"); $q->execute([':u'=>$idUsuario,':c'=>$l['id_curso']]); $cumple=(bool)$q->fetchColumn();
            }
            if ($cumple) {
                $ins->execute([':u'=>$idUsuario,':l'=>$l['id_logro'],':a'=>$idAsignacion]);
                if ($ins->rowCount()>0) {
                    $nuevos[]=$l;
                    $this->notificaciones->crear(
                        $idUsuario,
                        'logro_obtenido',
                        'Nuevo logro desbloqueado',
                        'Obtuviste el logro: ' . $l['nombre'] . '.',
                        '/DEVIOZ-VIDEOS/public/logros.php',
                        $l['icono'] ?: '🏅',
                        'logro:' . (int)$l['id_logro']
                    );
                }
            } else {
                // Mantiene los logros coherentes si una capacitación deja de cumplir
                // requisitos (por ejemplo, al publicar un examen final pendiente).
                $del->execute([':u'=>$idUsuario,':l'=>$l['id_logro']]);
            }
        }
        return $nuevos;
    }

    public function logrosUsuario(int $idUsuario): array
    {
        $this->evaluarLogrosUsuario($idUsuario,null);
        $sql="SELECT l.*,ul.fecha_obtenido,(ul.id_usuario_logro IS NOT NULL) AS obtenido,c.titulo AS curso FROM learning_logros l LEFT JOIN learning_usuario_logros ul ON ul.id_logro=l.id_logro AND ul.id_usuario=:u LEFT JOIN learning_cursos c ON c.id_curso=l.id_curso WHERE l.estado=1 ORDER BY obtenido DESC, l.id_logro";
        $stmt=$this->conexion->prepare($sql); $stmt->execute([':u'=>$idUsuario]); return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function resumenUsuario(int $idUsuario): array
    {
        $this->actualizarEstadosVencidos();
        $q=$this->conexion->prepare("SELECT COUNT(*) total,SUM(estado='completada') completadas,SUM(estado='en_progreso') en_progreso,SUM(estado='vencida') vencidas FROM learning_asignaciones WHERE id_usuario=:u"); $q->execute([':u'=>$idUsuario]); $a=$q->fetch(PDO::FETCH_ASSOC)?:[];
        $q=$this->conexion->prepare("SELECT COUNT(*) intentos,SUM(aprobado=1) aprobados,COALESCE(AVG(porcentaje),0) promedio FROM learning_intentos i INNER JOIN learning_asignaciones a ON a.id_asignacion=i.id_asignacion WHERE a.id_usuario=:u"); $q->execute([':u'=>$idUsuario]); $e=$q->fetch(PDO::FETCH_ASSOC)?:[];
        $q=$this->conexion->prepare("SELECT COUNT(*) FROM learning_usuario_logros WHERE id_usuario=:u"); $q->execute([':u'=>$idUsuario]); $logros=(int)$q->fetchColumn();
        return ['asignadas'=>(int)($a['total']??0),'completadas'=>(int)($a['completadas']??0),'en_progreso'=>(int)($a['en_progreso']??0),'vencidas'=>(int)($a['vencidas']??0),'intentos'=>(int)($e['intentos']??0),'aprobados'=>(int)($e['aprobados']??0),'promedio'=>round((float)($e['promedio']??0),1),'logros'=>$logros];
    }

    public function statsAdmin(): array
    {
        $this->actualizarEstadosVencidos();
        return [
            'cursos'=>(int)$this->conexion->query("SELECT COUNT(*) FROM learning_cursos WHERE estado<>'archivado'")->fetchColumn(),
            'capacitaciones'=>(int)$this->conexion->query("SELECT COUNT(*) FROM learning_capacitaciones WHERE estado='activa'")->fetchColumn(),
            'asignaciones'=>(int)$this->conexion->query("SELECT COUNT(*) FROM learning_asignaciones")->fetchColumn(),
            'completadas'=>(int)$this->conexion->query("SELECT COUNT(*) FROM learning_asignaciones WHERE estado='completada'")->fetchColumn(),
            'evaluaciones'=>(int)$this->conexion->query("SELECT COUNT(*) FROM learning_evaluaciones WHERE estado='publicada'")->fetchColumn(),
            'logros'=>(int)$this->conexion->query("SELECT COUNT(*) FROM learning_usuario_logros")->fetchColumn(),
        ];
    }

    public function seguimientoAdmin(): array
    {
        $this->actualizarEstadosVencidos();

        // V3.2.2 hotfix: las lecciones y los intentos se agregan por separado.
        // Antes, el JOIN directo contra learning_intentos multiplicaba las filas de
        // progreso por cada intento realizado (4 lecciones x 3 intentos = 300%).
        $sql="SELECT
                    a.id_asignacion,a.estado,a.fecha_asignacion,a.fecha_inicio_real,a.fecha_completado,
                    u.nombre,u.email,
                    cap.nombre AS capacitacion,cap.fecha_inicio,cap.fecha_limite,
                    c.id_curso,c.titulo AS curso,
                    COALESCE(lp.total_lecciones,0) AS total_lecciones,
                    COALESCE(lp.completadas,0) AS completadas,
                    e.id_evaluacion,e.intentos_permitidos,
                    ie.intentos_realizados,ie.intentos_aprobados,ie.mejor_nota
              FROM learning_asignaciones a
              INNER JOIN usuarios u ON u.id_usuario=a.id_usuario
              INNER JOIN learning_capacitaciones cap ON cap.id_capacitacion=a.id_capacitacion
              INNER JOIN learning_cursos c ON c.id_curso=cap.id_curso
              LEFT JOIN (
                    SELECT l.id_curso,pl.id_asignacion,
                           COUNT(*) AS total_lecciones,
                           SUM(CASE WHEN pl.estado='completada' THEN 1 ELSE 0 END) AS completadas
                    FROM learning_curso_lecciones l
                    INNER JOIN learning_asignaciones ax ON 1=1
                    INNER JOIN learning_capacitaciones capx ON capx.id_capacitacion=ax.id_capacitacion AND capx.id_curso=l.id_curso
                    LEFT JOIN learning_progreso_lecciones pl ON pl.id_asignacion=ax.id_asignacion AND pl.id_leccion=l.id_leccion
                    WHERE l.obligatoria=1
                    GROUP BY l.id_curso,ax.id_asignacion
              ) lp ON lp.id_curso=c.id_curso AND lp.id_asignacion=a.id_asignacion
              LEFT JOIN learning_evaluaciones e ON e.id_curso=c.id_curso AND e.estado='publicada'
              LEFT JOIN (
                    SELECT id_asignacion,id_evaluacion,COUNT(*) AS intentos_realizados,
                           SUM(CASE WHEN aprobado=1 THEN 1 ELSE 0 END) AS intentos_aprobados,
                           MAX(porcentaje) AS mejor_nota
                    FROM learning_intentos
                    GROUP BY id_asignacion,id_evaluacion
              ) ie ON ie.id_asignacion=a.id_asignacion AND ie.id_evaluacion=e.id_evaluacion
              ORDER BY FIELD(a.estado,'vencida','en_progreso','pendiente','completada'),cap.fecha_limite ASC";

        $rows=$this->conexion->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        foreach($rows as &$r){
            $total=(int)($r['total_lecciones']??0);
            $completadas=(int)($r['completadas']??0);
            $r['porcentaje']=$total>0?min(100,round(($completadas/$total)*100,1)):0;

            $r['intentos_realizados']=(int)($r['intentos_realizados']??0);
            $r['intentos_aprobados']=(int)($r['intentos_aprobados']??0);
            $r['intentos_permitidos']=$r['id_evaluacion']!==null?(int)$r['intentos_permitidos']:0;

            $leccionesCompletas=$total>0 && $completadas >= $total;
            $evaluacionPublicada=$r['id_evaluacion']!==null;
            $intentosAgotados=$evaluacionPublicada
                && $r['intentos_permitidos']>0
                && $r['intentos_realizados'] >= $r['intentos_permitidos'];
            $aprobo=$r['intentos_aprobados']>0;

            // Estado visual para seguimiento administrativo. No cambia el ENUM ni
            // altera la asignación: solo permite distinguir al usuario que terminó
            // las lecciones pero agotó el examen sin aprobar.
            $r['estado_base']=$r['estado'];
            if ($r['estado']==='completada' || $aprobo) {
                $r['estado']='completada';
            } elseif ($leccionesCompletas && $intentosAgotados && !$aprobo) {
                $r['estado']='desaprobada';
            }
        }
        unset($r);
        return $rows;
    }
}
