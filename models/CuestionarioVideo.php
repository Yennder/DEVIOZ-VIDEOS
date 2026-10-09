<?php

require_once __DIR__ . '/../config/conexion.php';

/** V4.5.4: cuestionarios publicos independientes de Learning Lab. */
class CuestionarioVideo
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? (new Conexion())->conectar();
    }

    public function instalado(): bool
    {
        try {
            return (bool)$this->db->query("SHOW TABLES LIKE 'video_cuestionarios'")->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    public function video(int $idVideo): ?array
    {
        $stmt = $this->db->prepare('SELECT id_video, titulo, estado FROM videos WHERE id_video = ? LIMIT 1');
        $stmt->execute([$idVideo]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function version(int $idVideo, string $estado): ?array
    {
        if (!in_array($estado, ['borrador', 'publicado'], true)) {
            throw new InvalidArgumentException('Estado de cuestionario no valido.');
        }
        $stmt = $this->db->prepare('SELECT * FROM video_cuestionarios WHERE id_video = ? AND estado = ? ORDER BY id_cuestionario DESC LIMIT 1');
        $stmt->execute([$idVideo, $estado]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @return array<int,array<string,mixed>> */
    public function preguntas(int $idCuestionario, bool $mostrarCorrectas = true): array
    {
        $sql = 'SELECT p.id_pregunta, p.orden, p.pregunta, p.explicacion,
                       o.id_opcion, o.orden AS orden_opcion, o.texto AS opcion_texto, o.es_correcta
                FROM video_cuestionario_preguntas p
                JOIN video_cuestionario_opciones o ON o.id_pregunta = p.id_pregunta
                WHERE p.id_cuestionario = ? ORDER BY p.orden, o.orden';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idCuestionario]);
        $preguntas = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $idPregunta = (int)$fila['id_pregunta'];
            if (!isset($preguntas[$idPregunta])) {
                $preguntas[$idPregunta] = [
                    'id_pregunta' => $idPregunta,
                    'orden' => (int)$fila['orden'],
                    'pregunta' => (string)$fila['pregunta'],
                    'explicacion' => (string)($fila['explicacion'] ?? ''),
                    'opciones' => [],
                ];
            }
            $opcion = [
                'id_opcion' => (int)$fila['id_opcion'],
                'orden' => (int)$fila['orden_opcion'],
                'texto' => (string)$fila['opcion_texto'],
            ];
            if ($mostrarCorrectas) {
                $opcion['es_correcta'] = (int)$fila['es_correcta'];
            }
            $preguntas[$idPregunta]['opciones'][] = $opcion;
        }
        return array_values($preguntas);
    }

    /** Valida exactamente cinco preguntas de opcion multiple, cuatro alternativas y una correcta. */
    public static function validarPreguntas(array $preguntas): array
    {
        if (count($preguntas) !== 5) {
            throw new InvalidArgumentException('El cuestionario debe contener exactamente cinco preguntas.');
        }
        $limite = static fn(string $s): int => function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
        $limpiar = static fn($s): string => trim((string)$s);
        $normalizadas = [];
        $vistas = [];
        foreach (array_values($preguntas) as $i => $fila) {
            if (!is_array($fila)) throw new InvalidArgumentException('Pregunta invalida.');
            $texto = $limpiar($fila['pregunta'] ?? '');
            $explicacion = $limpiar($fila['explicacion'] ?? '');
            if ($texto === '' || $limite($texto) > 800 || $limite($explicacion) > 2000) {
                throw new InvalidArgumentException('Revisa el texto de la pregunta ' . ($i + 1) . '.');
            }
            $clave = function_exists('mb_strtolower') ? mb_strtolower($texto, 'UTF-8') : strtolower($texto);
            if (isset($vistas[$clave])) throw new InvalidArgumentException('Las cinco preguntas deben ser diferentes.');
            $vistas[$clave] = true;
            $alternativas = $fila['opciones'] ?? null;
            if (!is_array($alternativas) || count($alternativas) !== 4) {
                throw new InvalidArgumentException('La pregunta ' . ($i + 1) . ' necesita cuatro alternativas.');
            }
            $correcta = filter_var($fila['correcta'] ?? null, FILTER_VALIDATE_INT);
            if ($correcta === false || $correcta < 0 || $correcta > 3) {
                throw new InvalidArgumentException('Selecciona una respuesta correcta para la pregunta ' . ($i + 1) . '.');
            }
            $opciones = [];
            $unicas = [];
            foreach (array_values($alternativas) as $j => $alternativa) {
                $t = $limpiar($alternativa);
                if ($t === '' || $limite($t) > 500) {
                    throw new InvalidArgumentException('Completa las cuatro alternativas de la pregunta ' . ($i + 1) . '.');
                }
                $k = function_exists('mb_strtolower') ? mb_strtolower($t, 'UTF-8') : strtolower($t);
                if (isset($unicas[$k])) throw new InvalidArgumentException('No repitas alternativas en la pregunta ' . ($i + 1) . '.');
                $unicas[$k] = true;
                $opciones[] = ['texto' => $t, 'es_correcta' => (int)$j === $correcta ? 1 : 0];
            }
            $normalizadas[] = ['pregunta' => $texto, 'explicacion' => $explicacion, 'opciones' => $opciones];
        }
        return $normalizadas;
    }

    /** Reemplaza solo el borrador, nunca toca la version publicada ni los intentos. */
    public function guardarBorrador(int $idVideo, array $preguntas, int $idAdmin, string $origen = 'manual', array $meta = []): int
    {
        $validas = self::validarPreguntas($preguntas);
        if ($idVideo < 1 || $idAdmin < 1 || !in_array($origen, ['manual', 'ia'], true)) {
            throw new InvalidArgumentException('Datos invalidos del cuestionario.');
        }
        $this->db->beginTransaction();
        try {
            // La fila del video serializa operaciones concurrentes sobre el mismo cuestionario.
            $lock = $this->db->prepare('SELECT id_video FROM videos WHERE id_video = ? FOR UPDATE');
            $lock->execute([$idVideo]);
            if (!$lock->fetchColumn()) throw new InvalidArgumentException('Video inexistente.');
            $borrador = $this->version($idVideo, 'borrador');
            if ($borrador) {
                $del = $this->db->prepare('DELETE FROM video_cuestionarios WHERE id_cuestionario = ? AND estado = ?');
                $del->execute([(int)$borrador['id_cuestionario'], 'borrador']);
            }
            $crear = $this->db->prepare('INSERT INTO video_cuestionarios (id_video, estado, origen, fuente_hash, proveedor, modelo, creado_por)
                                           VALUES (?, ?, ?, ?, ?, ?, ?)');
            $crear->execute([$idVideo, 'borrador', $origen, $meta['fuente_hash'] ?? null, $meta['proveedor'] ?? null, $meta['modelo'] ?? null, $idAdmin]);
            $id = (int)$this->db->lastInsertId();
            $q = $this->db->prepare('INSERT INTO video_cuestionario_preguntas (id_cuestionario, orden, pregunta, explicacion) VALUES (?, ?, ?, ?)');
            $op = $this->db->prepare('INSERT INTO video_cuestionario_opciones (id_pregunta, orden, texto, es_correcta) VALUES (?, ?, ?, ?)');
            foreach ($validas as $i => $pregunta) {
                $q->execute([$id, $i + 1, $pregunta['pregunta'], $pregunta['explicacion']]);
                $qid = (int)$this->db->lastInsertId();
                foreach ($pregunta['opciones'] as $j => $opcion) {
                    $op->execute([$qid, $j + 1, $opcion['texto'], $opcion['es_correcta']]);
                }
            }
            $this->db->commit();
            return $id;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function descartarBorrador(int $idVideo): void
    {
        $stmt = $this->db->prepare("DELETE FROM video_cuestionarios WHERE id_video = ? AND estado = 'borrador'");
        $stmt->execute([$idVideo]);
    }

    /** Se archiva la version anterior: sus intentos permanecen asociados a ella. */
    public function publicar(int $idVideo, int $idCuestionario, int $idAdmin, ?string $hashFuenteActual = null): void
    {
        $this->db->beginTransaction();
        try {
            $lock = $this->db->prepare('SELECT id_video FROM videos WHERE id_video = ? FOR UPDATE');
            $lock->execute([$idVideo]);
            if (!$lock->fetchColumn()) throw new InvalidArgumentException('Video no encontrado.');
            $b = $this->db->prepare('SELECT * FROM video_cuestionarios WHERE id_cuestionario = ? AND id_video = ? FOR UPDATE');
            $b->execute([$idCuestionario, $idVideo]);
            $version = $b->fetch(PDO::FETCH_ASSOC);
            if (!$version || $version['estado'] !== 'borrador') throw new InvalidArgumentException('El borrador ya no esta disponible.');
            if ($version['origen'] === 'ia' && ($hashFuenteActual === null || !hash_equals((string)$version['fuente_hash'], $hashFuenteActual))) {
                throw new InvalidArgumentException('La transcripcion cambio. Regenera las preguntas antes de publicar.');
            }
            $preguntas = $this->preguntas($idCuestionario);
            if (count($preguntas) !== 5) throw new InvalidArgumentException('Solo se publican cuestionarios con cinco preguntas.');
            foreach ($preguntas as $p) {
                if (count($p['opciones']) !== 4 || array_sum(array_column($p['opciones'], 'es_correcta')) !== 1) {
                    throw new InvalidArgumentException('Verifica las alternativas y respuestas correctas.');
                }
            }
            $archivar = $this->db->prepare("UPDATE video_cuestionarios SET estado = 'archivado' WHERE id_video = ? AND estado = 'publicado'");
            $archivar->execute([$idVideo]);
            $pub = $this->db->prepare("UPDATE video_cuestionarios SET estado = 'publicado', publicado_por = ?, fecha_publicacion = NOW() WHERE id_cuestionario = ? AND estado = 'borrador'");
            $pub->execute([$idAdmin, $idCuestionario]);
            if ($pub->rowCount() !== 1) throw new RuntimeException('No se pudo publicar el cuestionario.');
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function resumenUsuario(int $idVideo, int $idUsuario): array
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) AS intentos, COALESCE(MAX(i.puntaje),0) AS mejor_puntaje
              FROM video_cuestionario_intentos i JOIN video_cuestionarios c ON c.id_cuestionario = i.id_cuestionario
              WHERE c.id_video = ? AND i.id_usuario = ?');
        $stmt->execute([$idVideo, $idUsuario]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return ['intentos' => (int)($fila['intentos'] ?? 0), 'mejor_puntaje' => (int)($fila['mejor_puntaje'] ?? 0)];
    }

    /** Calificacion determinista, sin confiar en puntuaciones enviadas por el navegador. */
    public static function evaluar(array $preguntas, array $respuestas): array
    {
        if (count($preguntas) !== 5 || count($respuestas) !== 5) {
            throw new InvalidArgumentException('Responde las cinco preguntas antes de finalizar.');
        }
        $resultado = [];
        $aciertos = 0;
        foreach ($preguntas as $p) {
            $idPregunta = (int)$p['id_pregunta'];
            if (!array_key_exists($idPregunta, $respuestas) || !preg_match('/^[1-9][0-9]*$/', (string)$respuestas[$idPregunta])) {
                throw new InvalidArgumentException('Debes responder las cinco preguntas.');
            }
            $opcionId = (int)$respuestas[$idPregunta];
            $seleccion = null;
            foreach ($p['opciones'] as $op) {
                if ((int)$op['id_opcion'] === $opcionId) { $seleccion = $op; break; }
            }
            if (!$seleccion) throw new InvalidArgumentException('Una de las respuestas no pertenece a este cuestionario.');
            $ok = !empty($seleccion['es_correcta']);
            $aciertos += $ok ? 1 : 0;
            $resultado[] = ['id_pregunta' => $idPregunta, 'id_opcion' => $opcionId, 'es_correcta' => $ok ? 1 : 0];
        }
        return ['aciertos' => $aciertos, 'puntaje' => $aciertos * 20, 'respuestas' => $resultado];
    }

    /** Cada envio crea un intento inmutable; no altera las evaluaciones de cursos. */
    public function registrarIntento(int $idVideo, int $idUsuario, array $respuestas): array
    {
        if ($idUsuario < 1 || $idVideo < 1) throw new InvalidArgumentException('Usuario o video no valido.');
        $this->db->beginTransaction();
        try {
            // Evita el mismo numero de intento en envios simultaneos del usuario.
            $lock = $this->db->prepare('SELECT id_usuario FROM usuarios WHERE id_usuario = ? FOR UPDATE');
            $lock->execute([$idUsuario]);
            if (!$lock->fetchColumn()) throw new InvalidArgumentException('Tu sesion no es valida.');
            $stmt = $this->db->prepare("SELECT c.id_cuestionario FROM video_cuestionarios c JOIN videos v ON v.id_video = c.id_video
                      WHERE c.id_video = ? AND c.estado = 'publicado' AND v.estado = 'publicado' ORDER BY c.id_cuestionario DESC LIMIT 1 FOR UPDATE");
            $stmt->execute([$idVideo]);
            $idCuestionario = (int)$stmt->fetchColumn();
            if ($idCuestionario < 1) throw new InvalidArgumentException('Este video no tiene un cuestionario publicado.');
            $preguntas = $this->preguntas($idCuestionario);
            $evaluacion = self::evaluar($preguntas, $respuestas);
            $numero = $this->db->prepare('SELECT COALESCE(MAX(numero_intento), 0) + 1 FROM video_cuestionario_intentos WHERE id_cuestionario = ? AND id_usuario = ?');
            $numero->execute([$idCuestionario, $idUsuario]);
            $numeroIntento = (int)$numero->fetchColumn();
            $ins = $this->db->prepare('INSERT INTO video_cuestionario_intentos (id_cuestionario, id_usuario, numero_intento, aciertos, puntaje) VALUES (?, ?, ?, ?, ?)');
            $ins->execute([$idCuestionario, $idUsuario, $numeroIntento, $evaluacion['aciertos'], $evaluacion['puntaje']]);
            $idIntento = (int)$this->db->lastInsertId();
            $respuesta = $this->db->prepare('INSERT INTO video_cuestionario_respuestas (id_intento, id_pregunta, id_opcion, es_correcta) VALUES (?, ?, ?, ?)');
            foreach ($evaluacion['respuestas'] as $r) {
                $respuesta->execute([$idIntento, $r['id_pregunta'], $r['id_opcion'], $r['es_correcta']]);
            }
            $this->db->commit();
            return ['id_intento' => $idIntento, 'numero_intento' => $numeroIntento] + $evaluacion;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function intento(int $idIntento, int $idUsuario): ?array
    {
        $stmt = $this->db->prepare('SELECT i.*, c.id_video FROM video_cuestionario_intentos i JOIN video_cuestionarios c ON c.id_cuestionario = i.id_cuestionario WHERE i.id_intento = ? AND i.id_usuario = ? LIMIT 1');
        $stmt->execute([$idIntento, $idUsuario]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
