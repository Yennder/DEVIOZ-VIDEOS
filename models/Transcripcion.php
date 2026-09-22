<?php

require_once __DIR__ . '/../config/conexion.php';

class Transcripcion
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
            $stmt = $this->conexion->query("SHOW TABLES LIKE 'video_transcripciones'");
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    public function listarVideos(string $buscar = '', string $estado = ''): array
    {
        $sql = "
            SELECT
                v.id_video,
                v.titulo,
                v.archivo_video,
                v.tipo_contenido,
                v.estado AS video_estado,
                c.nombre AS categoria,
                s.titulo AS serie,
                t.numero_temporada,
                v.numero_capitulo,
                vt.id_transcripcion,
                COALESCE(vt.estado, 'no_generada') AS transcripcion_estado,
                COALESCE(vt.progreso, 0) AS progreso,
                vt.idioma,
                vt.proveedor,
                vt.modelo,
                vt.mensaje_error,
                vt.vtt_archivo,
                vt.fecha_actualizacion,
                (SELECT COUNT(*) FROM video_transcripcion_segmentos seg WHERE seg.id_transcripcion = vt.id_transcripcion) AS segmentos
            FROM videos v
            INNER JOIN categorias c ON c.id_categoria = v.id_categoria
            LEFT JOIN series s ON s.id_serie = v.id_serie
            LEFT JOIN temporadas t ON t.id_temporada = v.id_temporada
            LEFT JOIN video_transcripciones vt ON vt.id_video = v.id_video
            WHERE 1=1
        ";

        $params = [];

        if ($buscar !== '') {
            $sql .= " AND (v.titulo LIKE :buscar OR c.nombre LIKE :buscar_categoria OR s.titulo LIKE :buscar_serie) ";
            $like = '%' . $buscar . '%';
            $params[':buscar'] = $like;
            $params[':buscar_categoria'] = $like;
            $params[':buscar_serie'] = $like;
        }

        if ($estado !== '') {
            if ($estado === 'no_generada') {
                $sql .= " AND vt.id_transcripcion IS NULL ";
            } else {
                $sql .= " AND vt.estado = :estado ";
                $params[':estado'] = $estado;
            }
        }

        $sql .= " ORDER BY v.id_video DESC ";

        $stmt = $this->conexion->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerVideo(int $idVideo): ?array
    {
        $stmt = $this->conexion->prepare("SELECT * FROM videos WHERE id_video = :id LIMIT 1");
        $stmt->execute([':id' => $idVideo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function obtenerPorVideo(int $idVideo): ?array
    {
        if (!$this->tablasDisponibles()) {
            return null;
        }

        $stmt = $this->conexion->prepare("
            SELECT vt.*, v.titulo, v.archivo_video
            FROM video_transcripciones vt
            INNER JOIN videos v ON v.id_video = vt.id_video
            WHERE vt.id_video = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $idVideo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function obtenerSegmentosPorVideo(int $idVideo): array
    {
        if (!$this->tablasDisponibles()) {
            return [];
        }

        $stmt = $this->conexion->prepare("
            SELECT seg.*
            FROM video_transcripcion_segmentos seg
            INNER JOIN video_transcripciones vt ON vt.id_transcripcion = seg.id_transcripcion
            WHERE vt.id_video = :id
            ORDER BY seg.orden ASC, seg.inicio_segundos ASC
        ");
        $stmt->execute([':id' => $idVideo]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function encolar(int $idVideo, bool $forzar = false, string $idioma = 'es', string $modelo = 'small'): array
    {
        if (!$this->tablasDisponibles()) {
            throw new RuntimeException('Primero importa database/migracion_transcripcion_v3.sql.');
        }

        $video = $this->obtenerVideo($idVideo);
        if (!$video) {
            throw new RuntimeException('El video seleccionado no existe.');
        }

        $idioma = preg_match('/^[a-z]{2,5}$/i', $idioma) ? strtolower($idioma) : 'es';
        $modelo = in_array($modelo, ['tiny', 'base', 'small', 'medium', 'large-v3'], true) ? $modelo : 'small';

        $this->conexion->beginTransaction();

        try {
            $actual = $this->obtenerPorVideo($idVideo);

            if ($actual && !$forzar && in_array($actual['estado'], ['pendiente', 'procesando'], true)) {
                $this->conexion->commit();
                return $actual;
            }

            if ($actual) {
                $idTranscripcion = (int)$actual['id_transcripcion'];

                if ($forzar) {
                    $stmtDelete = $this->conexion->prepare('DELETE FROM video_transcripcion_segmentos WHERE id_transcripcion = :id');
                    $stmtDelete->execute([':id' => $idTranscripcion]);
                }

                $stmt = $this->conexion->prepare("
                    UPDATE video_transcripciones
                    SET idioma = :idioma,
                        proveedor = 'faster-whisper',
                        modelo = :modelo,
                        estado = 'pendiente',
                        progreso = 0,
                        mensaje_error = NULL,
                        texto_completo = CASE WHEN :forzar = 1 THEN NULL ELSE texto_completo END,
                        vtt_archivo = CASE WHEN :forzar2 = 1 THEN NULL ELSE vtt_archivo END,
                        fecha_inicio_proceso = NULL,
                        fecha_fin_proceso = NULL
                    WHERE id_transcripcion = :id
                ");
                $stmt->execute([
                    ':idioma' => $idioma,
                    ':modelo' => $modelo,
                    ':forzar' => $forzar ? 1 : 0,
                    ':forzar2' => $forzar ? 1 : 0,
                    ':id' => $idTranscripcion,
                ]);
            } else {
                $stmt = $this->conexion->prepare("
                    INSERT INTO video_transcripciones
                    (id_video, idioma, proveedor, modelo, estado, progreso)
                    VALUES (:video, :idioma, 'faster-whisper', :modelo, 'pendiente', 0)
                ");
                $stmt->execute([
                    ':video' => $idVideo,
                    ':idioma' => $idioma,
                    ':modelo' => $modelo,
                ]);
                $idTranscripcion = (int)$this->conexion->lastInsertId();
            }

            $stmtCancel = $this->conexion->prepare("
                UPDATE transcripcion_trabajos
                SET estado = 'cancelado', fecha_fin = NOW()
                WHERE id_video = :video AND estado IN ('pendiente','procesando')
            ");
            $stmtCancel->execute([':video' => $idVideo]);

            $stmtJob = $this->conexion->prepare("
                INSERT INTO transcripcion_trabajos
                (id_video, id_transcripcion, estado, progreso)
                VALUES (:video, :transcripcion, 'pendiente', 0)
            ");
            $stmtJob->execute([
                ':video' => $idVideo,
                ':transcripcion' => $idTranscripcion,
            ]);

            $this->conexion->commit();
            return $this->obtenerPorVideo($idVideo) ?? [];
        } catch (Throwable $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $e;
        }
    }

    public function actualizarTextosSegmentos(int $idVideo, array $textos): void
    {
        $transcripcion = $this->obtenerPorVideo($idVideo);
        if (!$transcripcion) {
            throw new RuntimeException('No existe una transcripcion para este video.');
        }

        $idTranscripcion = (int)$transcripcion['id_transcripcion'];
        $this->conexion->beginTransaction();

        try {
            $stmtUpdate = $this->conexion->prepare("
                UPDATE video_transcripcion_segmentos
                SET texto = :texto
                WHERE id_segmento = :segmento AND id_transcripcion = :transcripcion
            ");

            foreach ($textos as $idSegmento => $texto) {
                $idSegmento = (int)$idSegmento;
                $texto = trim((string)$texto);
                if ($idSegmento <= 0 || $texto === '') {
                    continue;
                }
                $stmtUpdate->execute([
                    ':texto' => mb_substr($texto, 0, 5000),
                    ':segmento' => $idSegmento,
                    ':transcripcion' => $idTranscripcion,
                ]);
            }

            $segmentos = $this->obtenerSegmentosPorVideo($idVideo);
            $textoCompleto = trim(implode("\n", array_map(
                static fn(array $seg): string => trim((string)$seg['texto']),
                $segmentos
            )));

            $stmtTrans = $this->conexion->prepare("
                UPDATE video_transcripciones
                SET texto_completo = :texto,
                    estado = 'completada',
                    progreso = 100,
                    mensaje_error = NULL,
                    fecha_fin_proceso = COALESCE(fecha_fin_proceso, NOW())
                WHERE id_transcripcion = :id
            ");
            $stmtTrans->execute([
                ':texto' => $textoCompleto,
                ':id' => $idTranscripcion,
            ]);

            $this->conexion->commit();
        } catch (Throwable $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $e;
        }
    }

    public function actualizarArchivoVtt(int $idVideo, string $archivo): void
    {
        $stmt = $this->conexion->prepare('UPDATE video_transcripciones SET vtt_archivo = :archivo WHERE id_video = :video');
        $stmt->execute([
            ':archivo' => basename($archivo),
            ':video' => $idVideo,
        ]);
    }

    public function generarVttDesdeVideo(int $idVideo): string
    {
        $segmentos = $this->obtenerSegmentosPorVideo($idVideo);
        $salida = "WEBVTT\n\n";
        $contador = 1;

        foreach ($segmentos as $seg) {
            $texto = trim((string)$seg['texto']);
            if ($texto === '') {
                continue;
            }
            $salida .= $contador++ . "\n";
            $salida .= $this->formatearTiempoVtt((float)$seg['inicio_segundos']) . ' --> ' . $this->formatearTiempoVtt((float)$seg['fin_segundos']) . "\n";
            $salida .= str_replace(["\r\n", "\r"], "\n", $texto) . "\n\n";
        }

        return $salida;
    }

    private function formatearTiempoVtt(float $segundos): string
    {
        $segundos = max(0, $segundos);
        $horas = (int)floor($segundos / 3600);
        $minutos = (int)floor(($segundos % 3600) / 60);
        $secs = $segundos - ($horas * 3600) - ($minutos * 60);
        return sprintf('%02d:%02d:%06.3f', $horas, $minutos, $secs);
    }

    public function obtenerContextoRelevante(int $idVideo, string $pregunta, int $limite = 10): array
    {
        $transcripcion = $this->obtenerPorVideo($idVideo);
        if (!$transcripcion || $transcripcion['estado'] !== 'completada') {
            return [
                'disponible' => false,
                'titulo' => $transcripcion['titulo'] ?? null,
                'contexto' => '',
                'segmentos' => [],
            ];
        }

        $segmentos = $this->obtenerSegmentosPorVideo($idVideo);
        if (!$segmentos) {
            return [
                'disponible' => false,
                'titulo' => $transcripcion['titulo'] ?? null,
                'contexto' => '',
                'segmentos' => [],
            ];
        }

        $preguntaNormalizada = $this->normalizar($pregunta);
        $esResumen = preg_match('/\b(resumen|resume|resumir|trata|contenido|explicacion general|de que)\b/i', $preguntaNormalizada) === 1;

        if ($esResumen) {
            $seleccionados = $this->muestrearSegmentos($segmentos, min(18, max(8, $limite + 4)));
        } else {
            $tokens = $this->tokensPregunta($preguntaNormalizada);
            $puntuados = [];

            foreach ($segmentos as $index => $segmento) {
                $textoNormalizado = $this->normalizar((string)$segmento['texto']);
                $score = 0;
                foreach ($tokens as $token) {
                    if ($token !== '' && str_contains($textoNormalizado, $token)) {
                        $score += 3;
                    }
                }

                if ($preguntaNormalizada !== '' && str_contains($textoNormalizado, $preguntaNormalizada)) {
                    $score += 10;
                }

                if ($score > 0) {
                    $puntuados[] = ['index' => $index, 'score' => $score];
                }
            }

            usort($puntuados, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
            $indices = [];
            foreach (array_slice($puntuados, 0, $limite) as $item) {
                $idx = (int)$item['index'];
                $indices[$idx] = true;
                if ($idx > 0) {
                    $indices[$idx - 1] = true;
                }
                if ($idx + 1 < count($segmentos)) {
                    $indices[$idx + 1] = true;
                }
            }

            if (!$indices) {
                $seleccionados = $this->muestrearSegmentos($segmentos, min(12, max(6, $limite)));
            } else {
                ksort($indices);
                $seleccionados = [];
                foreach (array_keys($indices) as $idx) {
                    $seleccionados[] = $segmentos[$idx];
                    if (count($seleccionados) >= 18) {
                        break;
                    }
                }
            }
        }

        $partes = [];
        foreach ($seleccionados as $seg) {
            $partes[] = '[' . $this->formatearTiempoHumano((float)$seg['inicio_segundos']) . ' - ' . $this->formatearTiempoHumano((float)$seg['fin_segundos']) . '] ' . trim((string)$seg['texto']);
        }

        $contexto = implode("\n", $partes);
        if (mb_strlen($contexto) > 14000) {
            $contexto = mb_substr($contexto, 0, 14000);
        }

        return [
            'disponible' => true,
            'titulo' => $transcripcion['titulo'],
            'contexto' => $contexto,
            'segmentos' => $seleccionados,
        ];
    }

    private function muestrearSegmentos(array $segmentos, int $cantidad): array
    {
        $total = count($segmentos);
        if ($total <= $cantidad) {
            return $segmentos;
        }

        $resultado = [];
        $paso = ($total - 1) / max(1, $cantidad - 1);
        $usados = [];
        for ($i = 0; $i < $cantidad; $i++) {
            $idx = (int)round($i * $paso);
            if (!isset($usados[$idx]) && isset($segmentos[$idx])) {
                $resultado[] = $segmentos[$idx];
                $usados[$idx] = true;
            }
        }
        return $resultado;
    }

    private function tokensPregunta(string $texto): array
    {
        $stop = [
            'que','como','cual','cuales','cuando','donde','quien','quienes','para','por','porque','con','sin','del','las','los','una','uno','unos','unas','este','esta','esto','ese','esa','sobre','video','dice','dijo','explica','explico','menciona','menciono','instructor','profesor','curso','leccion','parte','tema','algo','puede','podria','quiero','saber'
        ];
        $tokens = preg_split('/[^a-z0-9]+/i', $texto) ?: [];
        $tokens = array_values(array_unique(array_filter($tokens, static function(string $token) use ($stop): bool {
            return mb_strlen($token) >= 3 && !in_array($token, $stop, true);
        })));
        return array_slice($tokens, 0, 16);
    }

    private function normalizar(string $texto): string
    {
        $texto = mb_strtolower(trim($texto), 'UTF-8');
        $mapa = [
            'a' => ['a','á','à','ä','â'],
            'e' => ['e','é','è','ë','ê'],
            'i' => ['i','í','ì','ï','î'],
            'o' => ['o','ó','ò','ö','ô'],
            'u' => ['u','ú','ù','ü','û'],
            'n' => ['n','ñ'],
        ];
        foreach ($mapa as $reemplazo => $variantes) {
            $texto = str_replace($variantes, $reemplazo, $texto);
        }
        $texto = preg_replace('/[^a-z0-9\s]+/u', ' ', $texto) ?? $texto;
        $texto = preg_replace('/\s+/', ' ', $texto) ?? $texto;
        return trim($texto);
    }

    private function formatearTiempoHumano(float $segundos): string
    {
        $segundos = max(0, (int)round($segundos));
        $horas = intdiv($segundos, 3600);
        $resto = $segundos % 3600;
        $minutos = intdiv($resto, 60);
        $secs = $resto % 60;
        return $horas > 0
            ? sprintf('%02d:%02d:%02d', $horas, $minutos, $secs)
            : sprintf('%02d:%02d', $minutos, $secs);
    }
}
