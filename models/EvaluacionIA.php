<?php

require_once __DIR__ . '/../config/conexion.php';

class EvaluacionIA
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
            $stmt = $this->conexion->query("SHOW TABLES LIKE 'ai_evaluacion_borradores'");
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    public function curso(int $idCurso): ?array
    {
        $stmt = $this->conexion->prepare("SELECT id_curso,titulo,descripcion,nivel,duracion_estimada_minutos,fecha_actualizacion FROM learning_cursos WHERE id_curso=:id LIMIT 1");
        $stmt->execute([':id'=>$idCurso]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function leccionesCurso(int $idCurso): array
    {
        $stmt = $this->conexion->prepare("
            SELECT
                l.id_leccion,l.id_video,l.orden,l.titulo_personalizado,l.obligatoria,
                v.titulo AS video_titulo,v.descripcion AS video_descripcion,
                vt.estado AS transcripcion_estado,vt.fecha_actualizacion AS transcripcion_actualizacion,
                vt.duracion_segundos
            FROM learning_curso_lecciones l
            INNER JOIN videos v ON v.id_video=l.id_video
            LEFT JOIN video_transcripciones vt ON vt.id_video=v.id_video
            WHERE l.id_curso=:curso
            ORDER BY l.orden,l.id_leccion
        ");
        $stmt->execute([':curso'=>$idCurso]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function segmentosVideo(int $idVideo): array
    {
        $stmt = $this->conexion->prepare("
            SELECT seg.orden,seg.inicio_segundos,seg.fin_segundos,seg.texto
            FROM video_transcripcion_segmentos seg
            INNER JOIN video_transcripciones vt ON vt.id_transcripcion=seg.id_transcripcion
            WHERE vt.id_video=:video AND vt.estado='completada'
            ORDER BY seg.orden,seg.inicio_segundos
        ");
        $stmt->execute([':video'=>$idVideo]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function preguntasExistentesCurso(int $idCurso): array
    {
        $stmt = $this->conexion->prepare("
            SELECT p.pregunta
            FROM learning_preguntas p
            INNER JOIN learning_evaluaciones e ON e.id_evaluacion=p.id_evaluacion
            WHERE e.id_curso=:curso
            ORDER BY p.id_pregunta
        ");
        $stmt->execute([':curso'=>$idCurso]);
        return array_values(array_filter(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN))));
    }

    public function descartarBorradoresAbiertos(int $idCurso): void
    {
        if (!$this->tablaDisponible()) return;
        $stmt = $this->conexion->prepare("UPDATE ai_evaluacion_borradores SET estado='descartado',fecha_actualizacion=NOW() WHERE id_curso=:curso AND estado='borrador'");
        $stmt->execute([':curso'=>$idCurso]);
    }

    public function crearBorrador(int $idCurso, array $config, array $preguntas, array $meta, int $idUsuario): array
    {
        if (!$this->tablaDisponible()) {
            throw new RuntimeException('Primero importa database/migracion_v4_4_2_evaluaciones_ia.sql.');
        }

        $this->conexion->beginTransaction();
        try {
            $this->descartarBorradoresAbiertos($idCurso);
            $stmt = $this->conexion->prepare("
                INSERT INTO ai_evaluacion_borradores
                (id_curso,cantidad_solicitada,dificultad,tipos,alcance,lecciones_ids,fuente_hash,fuente_items,proveedor,modelo,estado,creado_por)
                VALUES (:curso,:cantidad,:dificultad,:tipos,:alcance,:lecciones,:hash,:items,:proveedor,:modelo,'borrador',:usuario)
            ");
            $stmt->execute([
                ':curso'=>$idCurso,
                ':cantidad'=>(int)$config['cantidad'],
                ':dificultad'=>(string)$config['dificultad'],
                ':tipos'=>json_encode(array_values($config['tipos']), JSON_UNESCAPED_UNICODE),
                ':alcance'=>(string)$config['alcance'],
                ':lecciones'=>json_encode(array_values($config['lecciones_ids']), JSON_UNESCAPED_UNICODE),
                ':hash'=>(string)$meta['fuente_hash'],
                ':items'=>(int)$meta['fuente_items'],
                ':proveedor'=>$meta['proveedor'] ?? null,
                ':modelo'=>$meta['modelo'] ?? null,
                ':usuario'=>$idUsuario > 0 ? $idUsuario : null,
            ]);
            $idBorrador = (int)$this->conexion->lastInsertId();

            $insP = $this->conexion->prepare("
                INSERT INTO ai_evaluacion_preguntas
                (id_borrador,pregunta,tipo,dificultad,puntos,explicacion,orden)
                VALUES (:b,:pregunta,:tipo,:dificultad,:puntos,:explicacion,:orden)
            ");
            $insO = $this->conexion->prepare("
                INSERT INTO ai_evaluacion_opciones
                (id_pregunta_ia,texto,es_correcta,orden)
                VALUES (:p,:texto,:correcta,:orden)
            ");

            foreach ($preguntas as $idx=>$pregunta) {
                $insP->execute([
                    ':b'=>$idBorrador,
                    ':pregunta'=>(string)$pregunta['pregunta'],
                    ':tipo'=>(string)$pregunta['tipo'],
                    ':dificultad'=>(string)$pregunta['dificultad'],
                    ':puntos'=>(float)($pregunta['puntos'] ?? 1),
                    ':explicacion'=>(string)($pregunta['explicacion'] ?? ''),
                    ':orden'=>$idx+1,
                ]);
                $idPregunta = (int)$this->conexion->lastInsertId();
                foreach ($pregunta['opciones'] as $oIdx=>$opcion) {
                    $insO->execute([
                        ':p'=>$idPregunta,
                        ':texto'=>(string)$opcion['texto'],
                        ':correcta'=>!empty($opcion['correcta']) ? 1 : 0,
                        ':orden'=>$oIdx+1,
                    ]);
                }
            }

            $this->conexion->commit();
            return $this->obtenerBorrador($idBorrador) ?? [];
        } catch (Throwable $e) {
            if ($this->conexion->inTransaction()) $this->conexion->rollBack();
            throw $e;
        }
    }

    public function ultimoBorradorCurso(int $idCurso): ?array
    {
        if (!$this->tablaDisponible()) return null;
        $stmt = $this->conexion->prepare("SELECT id_borrador FROM ai_evaluacion_borradores WHERE id_curso=:curso AND estado='borrador' ORDER BY id_borrador DESC LIMIT 1");
        $stmt->execute([':curso'=>$idCurso]);
        $id = (int)($stmt->fetchColumn() ?: 0);
        return $id > 0 ? $this->obtenerBorrador($id) : null;
    }

    public function obtenerBorrador(int $idBorrador): ?array
    {
        if (!$this->tablaDisponible()) return null;
        $stmt = $this->conexion->prepare("
            SELECT b.*,c.titulo AS curso
            FROM ai_evaluacion_borradores b
            INNER JOIN learning_cursos c ON c.id_curso=b.id_curso
            WHERE b.id_borrador=:id LIMIT 1
        ");
        $stmt->execute([':id'=>$idBorrador]);
        $b = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$b) return null;

        foreach (['tipos','lecciones_ids'] as $campo) {
            $v = json_decode((string)($b[$campo] ?? '[]'), true);
            $b[$campo] = is_array($v) ? array_values($v) : [];
        }

        $q = $this->conexion->prepare("SELECT * FROM ai_evaluacion_preguntas WHERE id_borrador=:b ORDER BY orden,id_pregunta_ia");
        $q->execute([':b'=>$idBorrador]);
        $preguntas = $q->fetchAll(PDO::FETCH_ASSOC);
        $op = $this->conexion->prepare("SELECT * FROM ai_evaluacion_opciones WHERE id_pregunta_ia=:p ORDER BY orden,id_opcion_ia");
        foreach ($preguntas as &$p) {
            $op->execute([':p'=>$p['id_pregunta_ia']]);
            $p['opciones'] = $op->fetchAll(PDO::FETCH_ASSOC);
        }
        unset($p);
        $b['preguntas'] = $preguntas;
        return $b;
    }

    public function actualizarPreguntaBorrador(int $idPregunta, int $idCurso, array $datos): void
    {
        $stmt = $this->conexion->prepare("
            SELECT p.*,b.id_curso,b.estado
            FROM ai_evaluacion_preguntas p
            INNER JOIN ai_evaluacion_borradores b ON b.id_borrador=p.id_borrador
            WHERE p.id_pregunta_ia=:id AND b.id_curso=:curso LIMIT 1
        ");
        $stmt->execute([':id'=>$idPregunta,':curso'=>$idCurso]);
        $actual = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$actual || $actual['estado'] !== 'borrador') throw new RuntimeException('La pregunta IA ya no puede editarse.');

        $pregunta = trim((string)($datos['pregunta'] ?? ''));
        if ($pregunta === '') throw new InvalidArgumentException('La pregunta no puede quedar vacía.');
        $pregunta = mb_substr($pregunta,0,800,'UTF-8');
        $tipo = in_array(($datos['tipo'] ?? ''), ['opcion_multiple','verdadero_falso'], true) ? $datos['tipo'] : 'opcion_multiple';
        $dificultad = in_array(($datos['dificultad'] ?? ''), ['basica','intermedia','avanzada'], true) ? $datos['dificultad'] : 'intermedia';
        $puntos = max(.1, min(100, (float)($datos['puntos'] ?? 1)));
        $explicacion = mb_substr(trim((string)($datos['explicacion'] ?? '')),0,1500,'UTF-8');

        $opciones = [];
        $correcta = 0;
        if ($tipo === 'verdadero_falso') {
            $opciones = ['Verdadero','Falso'];
            $correcta = (($datos['respuesta_vf'] ?? 'verdadero') === 'falso') ? 1 : 0;
        } else {
            for ($i=1;$i<=4;$i++) {
                $txt = mb_substr(trim((string)($datos['opcion_'.$i] ?? '')),0,500,'UTF-8');
                if ($txt !== '') $opciones[] = $txt;
            }
            $correcta = max(0, (int)($datos['correcta'] ?? 1)-1);
            if (count($opciones) < 2 || !isset($opciones[$correcta])) {
                throw new InvalidArgumentException('Agrega al menos dos opciones y marca una respuesta correcta válida.');
            }
        }

        $this->conexion->beginTransaction();
        try {
            $up = $this->conexion->prepare("UPDATE ai_evaluacion_preguntas SET pregunta=:q,tipo=:tipo,dificultad=:dif,puntos=:pts,explicacion=:exp WHERE id_pregunta_ia=:id");
            $up->execute([':q'=>$pregunta,':tipo'=>$tipo,':dif'=>$dificultad,':pts'=>$puntos,':exp'=>$explicacion,':id'=>$idPregunta]);
            $this->conexion->prepare("DELETE FROM ai_evaluacion_opciones WHERE id_pregunta_ia=:id")->execute([':id'=>$idPregunta]);
            $ins = $this->conexion->prepare("INSERT INTO ai_evaluacion_opciones (id_pregunta_ia,texto,es_correcta,orden) VALUES (:p,:t,:c,:o)");
            foreach ($opciones as $idx=>$txt) {
                $ins->execute([':p'=>$idPregunta,':t'=>$txt,':c'=>$idx===$correcta?1:0,':o'=>$idx+1]);
            }
            $this->conexion->commit();
        } catch (Throwable $e) {
            if ($this->conexion->inTransaction()) $this->conexion->rollBack();
            throw $e;
        }
    }

    public function eliminarPreguntaBorrador(int $idPregunta, int $idCurso): void
    {
        $stmt = $this->conexion->prepare("
            DELETE p FROM ai_evaluacion_preguntas p
            INNER JOIN ai_evaluacion_borradores b ON b.id_borrador=p.id_borrador
            WHERE p.id_pregunta_ia=:id AND b.id_curso=:curso AND b.estado='borrador'
        ");
        $stmt->execute([':id'=>$idPregunta,':curso'=>$idCurso]);
        if ($stmt->rowCount() < 1) throw new RuntimeException('No se pudo eliminar la pregunta del borrador.');
    }

    public function descartarBorrador(int $idBorrador, int $idCurso): void
    {
        $stmt = $this->conexion->prepare("UPDATE ai_evaluacion_borradores SET estado='descartado',fecha_actualizacion=NOW() WHERE id_borrador=:id AND id_curso=:curso AND estado='borrador'");
        $stmt->execute([':id'=>$idBorrador,':curso'=>$idCurso]);
        if ($stmt->rowCount() < 1) throw new RuntimeException('El borrador no se pudo descartar.');
    }

    public function aprobarBorrador(int $idBorrador, int $idCurso, int $idUsuario): array
    {
        $borrador = $this->obtenerBorrador($idBorrador);
        if (!$borrador || (int)$borrador['id_curso'] !== $idCurso || $borrador['estado'] !== 'borrador') {
            throw new RuntimeException('El borrador IA no está disponible para aprobación.');
        }
        if (empty($borrador['preguntas'])) throw new RuntimeException('El borrador no tiene preguntas para agregar.');

        $eval = $this->conexion->prepare("SELECT id_evaluacion,estado FROM learning_evaluaciones WHERE id_curso=:curso LIMIT 1");
        $eval->execute([':curso'=>$idCurso]);
        $evaluacion = $eval->fetch(PDO::FETCH_ASSOC);
        if (!$evaluacion) throw new RuntimeException('Guarda primero la configuración de la evaluación final.');

        $this->conexion->beginTransaction();
        try {
            $ord = $this->conexion->prepare("SELECT COALESCE(MAX(orden),0) FROM learning_preguntas WHERE id_evaluacion=:e");
            $ord->execute([':e'=>$evaluacion['id_evaluacion']]);
            $orden = (int)$ord->fetchColumn();

            $insP = $this->conexion->prepare("
                INSERT INTO learning_preguntas
                (id_evaluacion,pregunta,tipo,puntos,orden,origen,dificultad,explicacion,id_borrador_ia)
                VALUES (:e,:q,:tipo,:pts,:orden,'ia',:dif,:exp,:b)
            ");
            $insO = $this->conexion->prepare("INSERT INTO learning_opciones (id_pregunta,texto,es_correcta,orden) VALUES (:p,:t,:c,:o)");

            $agregadas = 0;
            foreach ($borrador['preguntas'] as $p) {
                $orden++;
                $insP->execute([
                    ':e'=>$evaluacion['id_evaluacion'],
                    ':q'=>$p['pregunta'],
                    ':tipo'=>$p['tipo'],
                    ':pts'=>$p['puntos'],
                    ':orden'=>$orden,
                    ':dif'=>$p['dificultad'],
                    ':exp'=>$p['explicacion'],
                    ':b'=>$idBorrador,
                ]);
                $idPregunta = (int)$this->conexion->lastInsertId();
                foreach ($p['opciones'] as $o) {
                    $insO->execute([':p'=>$idPregunta,':t'=>$o['texto'],':c'=>(int)$o['es_correcta'],':o'=>(int)$o['orden']]);
                }
                $agregadas++;
            }

            $up = $this->conexion->prepare("UPDATE ai_evaluacion_borradores SET estado='aprobado',aprobado_por=:u,fecha_aprobacion=NOW(),fecha_actualizacion=NOW() WHERE id_borrador=:id");
            $up->execute([':u'=>$idUsuario > 0 ? $idUsuario : null, ':id'=>$idBorrador]);
            $this->conexion->commit();
            return ['agregadas'=>$agregadas,'evaluacion_publicada'=>$evaluacion['estado']==='publicada'];
        } catch (Throwable $e) {
            if ($this->conexion->inTransaction()) $this->conexion->rollBack();
            throw $e;
        }
    }
}
