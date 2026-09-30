<?php

require_once __DIR__ . '/../models/ResumenIA.php';
require_once __DIR__ . '/../AIManager/AIManager.php';

class ResumenIAService
{
    private ResumenIA $model;

    public function __construct()
    {
        $this->model = new ResumenIA();
    }

    public function estado(string $tipo, int $id): array
    {
        $tipo = $this->normalizarTipo($tipo);
        $fuente = $this->prepararFuente($tipo, $id, false);
        $resumen = $this->model->obtener($tipo, $id);

        $desactualizado = false;
        if ($resumen && !empty($fuente['hash'])) {
            $desactualizado = !hash_equals((string)$resumen['fuente_hash'], (string)$fuente['hash']);
        }

        return [
            'ok' => true,
            'tipo' => $tipo,
            'id' => $id,
            'disponible' => (bool)$fuente['disponible'],
            'motivo' => (string)($fuente['motivo'] ?? ''),
            'cobertura' => $fuente['cobertura'] ?? null,
            'resumen' => $resumen,
            'desactualizado' => $desactualizado,
        ];
    }

    public function generar(string $tipo, int $id, int $idUsuario, bool $forzar = false): array
    {
        $tipo = $this->normalizarTipo($tipo);
        if (!$this->model->tablaDisponible()) {
            throw new RuntimeException('Primero importa database/migracion_v4_4_1_resumenes_ia.sql.');
        }

        $fuente = $this->prepararFuente($tipo, $id, true);
        if (!$fuente['disponible']) {
            throw new RuntimeException((string)($fuente['motivo'] ?: 'No hay contenido suficiente para generar el resumen.'));
        }

        $existente = $this->model->obtener($tipo, $id);
        if ($existente && !$forzar && hash_equals((string)$existente['fuente_hash'], (string)$fuente['hash'])) {
            return [
                'ok' => true,
                'cache' => true,
                'resumen' => $existente,
                'cobertura' => $fuente['cobertura'],
            ];
        }

        $mensajes = $this->crearMensajes($tipo, $fuente);
        $ai = new AIManager();
        $resultado = $ai->generarConFallback($mensajes, ['groq', 'gemini'], [
            'temperature' => 0.2,
            'max_tokens' => 1400,
        ]);

        if (empty($resultado['ok'])) {
            throw new RuntimeException((string)($resultado['mensaje'] ?? 'DEVIOZ AI no pudo generar el resumen.'));
        }

        $contenido = $this->parsearRespuesta((string)($resultado['respuesta'] ?? ''));
        $guardado = $this->model->guardar(
            $tipo,
            $id,
            $contenido,
            (string)$fuente['hash'],
            (int)$fuente['items'],
            isset($resultado['provider']) ? (string)$resultado['provider'] : null,
            isset($resultado['model']) ? (string)$resultado['model'] : null,
            $idUsuario
        );

        return [
            'ok' => true,
            'cache' => false,
            'resumen' => $guardado,
            'cobertura' => $fuente['cobertura'],
        ];
    }

    private function normalizarTipo(string $tipo): string
    {
        $tipo = strtolower(trim($tipo));
        if (!in_array($tipo, ['video', 'curso'], true)) {
            throw new InvalidArgumentException('Tipo de resumen no válido.');
        }
        return $tipo;
    }

    private function prepararFuente(string $tipo, int $id, bool $incluirTexto): array
    {
        if ($id <= 0) {
            return ['disponible' => false, 'motivo' => 'Contenido inválido.', 'hash' => '', 'items' => 0, 'cobertura' => null, 'texto' => ''];
        }

        return $tipo === 'video'
            ? $this->fuenteVideo($id, $incluirTexto)
            : $this->fuenteCurso($id, $incluirTexto);
    }

    private function fuenteVideo(int $idVideo, bool $incluirTexto): array
    {
        $video = $this->model->video($idVideo);
        if (!$video) {
            return ['disponible' => false, 'motivo' => 'El video no existe.', 'hash' => '', 'items' => 0, 'cobertura' => null, 'texto' => ''];
        }

        $completada = ($video['transcripcion_estado'] ?? '') === 'completada';
        $hash = hash('sha256', implode('|', [
            'video',
            $idVideo,
            (string)($video['transcripcion_actualizacion'] ?? ''),
            (string)($video['duracion_segundos'] ?? ''),
        ]));

        if (!$completada) {
            return [
                'disponible' => false,
                'motivo' => 'Este video necesita una transcripción completada antes de generar el resumen.',
                'hash' => $hash,
                'items' => 0,
                'cobertura' => ['transcritas' => 0, 'total' => 1],
                'texto' => '',
            ];
        }

        $segmentos = $incluirTexto ? $this->model->segmentosVideo($idVideo) : [];
        $texto = $incluirTexto ? $this->muestrearSegmentos($segmentos, 18000) : '';

        return [
            'disponible' => true,
            'motivo' => '',
            'hash' => $hash,
            'items' => 1,
            'cobertura' => ['transcritas' => 1, 'total' => 1],
            'titulo' => (string)$video['titulo'],
            'descripcion' => (string)($video['descripcion'] ?? ''),
            'meta' => trim(implode(' · ', array_filter([
                (string)($video['categoria'] ?? ''),
                (string)($video['serie'] ?? ''),
                !empty($video['numero_temporada']) ? 'T' . $video['numero_temporada'] : '',
                !empty($video['numero_capitulo']) ? 'C' . $video['numero_capitulo'] : '',
            ]))),
            'texto' => $texto,
        ];
    }

    private function fuenteCurso(int $idCurso, bool $incluirTexto): array
    {
        $curso = $this->model->curso($idCurso);
        if (!$curso) {
            return ['disponible' => false, 'motivo' => 'El curso no existe.', 'hash' => '', 'items' => 0, 'cobertura' => null, 'texto' => ''];
        }

        $videos = $this->model->videosCurso($idCurso);
        if (!$videos) {
            return [
                'disponible' => false,
                'motivo' => 'El curso todavía no tiene lecciones.',
                'hash' => hash('sha256', 'curso|' . $idCurso . '|sin-lecciones'),
                'items' => 0,
                'cobertura' => ['transcritas' => 0, 'total' => 0],
                'texto' => '',
            ];
        }

        $hashPartes = ['curso', $idCurso, (string)($curso['fecha_actualizacion'] ?? '')];
        $transcritas = 0;
        foreach ($videos as $video) {
            $hashPartes[] = implode(':', [
                (int)$video['id_video'],
                (int)$video['orden'],
                (string)($video['titulo_personalizado'] ?? ''),
                (string)($video['transcripcion_actualizacion'] ?? ''),
                (string)($video['transcripcion_estado'] ?? ''),
            ]);
            if (($video['transcripcion_estado'] ?? '') === 'completada') {
                $transcritas++;
            }
        }
        $hash = hash('sha256', implode('|', $hashPartes));

        if ($transcritas === 0) {
            return [
                'disponible' => false,
                'motivo' => 'Ninguna lección del curso tiene una transcripción completada.',
                'hash' => $hash,
                'items' => 0,
                'cobertura' => ['transcritas' => 0, 'total' => count($videos)],
                'texto' => '',
            ];
        }

        $bloques = [];
        if ($incluirTexto) {
            $maxTotal = 26000;
            $porVideo = max(1800, min(5000, (int)floor($maxTotal / max(1, $transcritas))));

            foreach ($videos as $video) {
                if (($video['transcripcion_estado'] ?? '') !== 'completada') {
                    continue;
                }
                $segmentos = $this->model->segmentosVideo((int)$video['id_video']);
                $extracto = $this->muestrearSegmentos($segmentos, $porVideo);
                if ($extracto === '') {
                    continue;
                }
                $tituloLeccion = trim((string)($video['titulo_personalizado'] ?: $video['titulo']));
                $bloques[] = "LECCIÓN " . (int)$video['orden'] . ": " . $tituloLeccion . "\n" . $extracto;
            }
        }

        return [
            'disponible' => true,
            'motivo' => $transcritas < count($videos) ? 'El resumen se genera con las lecciones que ya están transcritas.' : '',
            'hash' => $hash,
            'items' => $transcritas,
            'cobertura' => ['transcritas' => $transcritas, 'total' => count($videos)],
            'titulo' => (string)$curso['titulo'],
            'descripcion' => (string)($curso['descripcion'] ?? ''),
            'meta' => ucfirst((string)$curso['nivel']) . ' · ' . (int)$curso['duracion_estimada_minutos'] . ' min',
            'texto' => implode("\n\n", $bloques),
        ];
    }

    private function muestrearSegmentos(array $segmentos, int $maxCaracteres): string
    {
        $segmentos = array_values(array_filter($segmentos, static fn(array $s): bool => trim((string)($s['texto'] ?? '')) !== ''));
        if (!$segmentos) {
            return '';
        }

        $lineas = [];
        $total = count($segmentos);
        $objetivo = min($total, 42);
        $indices = [];

        if ($total <= $objetivo) {
            $indices = range(0, $total - 1);
        } else {
            for ($i = 0; $i < $objetivo; $i++) {
                $indices[] = (int)round($i * ($total - 1) / max(1, $objetivo - 1));
            }
            $indices = array_values(array_unique($indices));
        }

        $caracteres = 0;
        foreach ($indices as $indice) {
            $seg = $segmentos[$indice];
            $texto = preg_replace('/\s+/u', ' ', trim((string)$seg['texto'])) ?: '';
            if ($texto === '') {
                continue;
            }
            $tiempo = $this->formatearTiempo((float)($seg['inicio_segundos'] ?? 0));
            $linea = '[' . $tiempo . '] ' . $texto;
            $largo = mb_strlen($linea, 'UTF-8') + 1;
            if ($caracteres + $largo > $maxCaracteres && $lineas) {
                break;
            }
            $lineas[] = $linea;
            $caracteres += $largo;
        }

        return implode("\n", $lineas);
    }

    private function formatearTiempo(float $segundos): string
    {
        $total = max(0, (int)round($segundos));
        $h = intdiv($total, 3600);
        $resto = $total % 3600;
        $m = intdiv($resto, 60);
        $s = $resto % 60;
        return $h > 0 ? sprintf('%02d:%02d:%02d', $h, $m, $s) : sprintf('%02d:%02d', $m, $s);
    }

    private function crearMensajes(string $tipo, array $fuente): array
    {
        $alcance = $tipo === 'curso' ? 'curso completo' : 'video';
        $system = <<<TXT
Eres DEVIOZ AI actuando como analista educativo. Debes resumir un {$alcance} usando EXCLUSIVAMENTE el contenido fuente entregado. No inventes información que no aparezca o no pueda inferirse directamente de la fuente. Si una tecnología o concepto no se menciona claramente, no lo agregues.

Devuelve SOLO JSON válido en UTF-8, sin markdown, sin bloques ``` y con esta estructura exacta:
{"resumen":"texto claro de 2 a 4 párrafos cortos","puntos_clave":["punto 1"],"conceptos":["concepto 1"],"tecnologias":["tecnología 1"]}

Reglas: máximo 6 puntos clave, 8 conceptos y 8 tecnologías. Usa lenguaje claro para aprendizaje. Si no hay tecnologías explícitas, devuelve [] en tecnologias.
TXT;

        $user = "TÍTULO: " . $fuente['titulo'] . "\n";
        if (!empty($fuente['meta'])) {
            $user .= "CONTEXTO: " . $fuente['meta'] . "\n";
        }
        if (!empty($fuente['descripcion'])) {
            $user .= "DESCRIPCIÓN: " . $fuente['descripcion'] . "\n";
        }
        if (!empty($fuente['cobertura']) && ($fuente['cobertura']['total'] ?? 0) > 1) {
            $user .= "COBERTURA: " . (int)$fuente['cobertura']['transcritas'] . " de " . (int)$fuente['cobertura']['total'] . " lecciones transcritas.\n";
        }
        $user .= "\nFUENTE TRANSCRITA:\n" . $fuente['texto'];

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ];
    }

    private function parsearRespuesta(string $texto): array
    {
        $texto = trim($texto);
        $texto = preg_replace('/^```(?:json)?\s*/i', '', $texto) ?? $texto;
        $texto = preg_replace('/\s*```$/', '', $texto) ?? $texto;

        $datos = json_decode($texto, true);
        if (!is_array($datos)) {
            $inicio = strpos($texto, '{');
            $fin = strrpos($texto, '}');
            if ($inicio !== false && $fin !== false && $fin > $inicio) {
                $datos = json_decode(substr($texto, $inicio, $fin - $inicio + 1), true);
            }
        }

        if (!is_array($datos) || trim((string)($datos['resumen'] ?? '')) === '') {
            throw new RuntimeException('La IA devolvió un formato de resumen inválido. Intenta nuevamente.');
        }

        return [
            'resumen' => mb_substr(trim((string)$datos['resumen']), 0, 8000, 'UTF-8'),
            'puntos_clave' => $this->limpiarLista($datos['puntos_clave'] ?? [], 6),
            'conceptos' => $this->limpiarLista($datos['conceptos'] ?? [], 8),
            'tecnologias' => $this->limpiarLista($datos['tecnologias'] ?? [], 8),
        ];
    }

    private function limpiarLista(mixed $valor, int $limite): array
    {
        if (!is_array($valor)) {
            return [];
        }
        $salida = [];
        foreach ($valor as $item) {
            $item = trim((string)$item);
            if ($item === '') {
                continue;
            }
            $salida[] = mb_substr($item, 0, 300, 'UTF-8');
            if (count($salida) >= $limite) {
                break;
            }
        }
        return array_values(array_unique($salida));
    }
}
