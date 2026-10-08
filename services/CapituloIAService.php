<?php

require_once __DIR__ . '/../models/CapituloIA.php';
require_once __DIR__ . '/../AIManager/AIManager.php';

class CapituloIAService
{
    private CapituloIA $model;

    public function __construct()
    {
        $this->model = new CapituloIA();
    }

    public function tablasDisponibles(): bool
    {
        return $this->model->tablasDisponibles();
    }

    public function publicadosVideo(int $idVideo): array
    {
        if ($idVideo <= 0 || !$this->model->tablasDisponibles()) {
            return [];
        }
        $publicacion = $this->model->publicacionVideo($idVideo);
        return $publicacion['capitulos'] ?? [];
    }

    public function estadoAdmin(int $idVideo): array
    {
        if ($idVideo <= 0) {
            throw new InvalidArgumentException('Video inválido.');
        }

        $video = $this->model->video($idVideo);
        if (!$video) {
            throw new RuntimeException('El video seleccionado no existe.');
        }

        if (!$this->model->tablasDisponibles()) {
            return [
                'instalado' => false,
                'video' => $video,
                'fuente_disponible' => false,
                'motivo' => 'Primero importa database/migracion_v4_4_3_capitulos_inteligentes.sql.',
                'duracion_segundos' => (float)($video['duracion_segundos'] ?? 0),
                'segmentos' => 0,
                'fuente_hash' => '',
                'borrador' => null,
                'publicada' => null,
                'borrador_desactualizado' => false,
                'publicada_desactualizada' => false,
            ];
        }

        $fuente = $this->prepararFuente($idVideo, false);
        $borrador = $this->model->obtenerGeneracionVideo($idVideo, 'borrador');
        $publicada = $this->model->obtenerGeneracionVideo($idVideo, 'publicada');

        $borradorCompleto = $borrador ? [
            'generacion' => $borrador,
            'capitulos' => $this->model->capitulosGeneracion((int)$borrador['id_generacion']),
        ] : null;

        $publicadaCompleta = $publicada ? [
            'generacion' => $publicada,
            'capitulos' => $this->model->capitulosGeneracion((int)$publicada['id_generacion']),
        ] : null;

        $hash = (string)($fuente['hash'] ?? '');

        return [
            'instalado' => true,
            'video' => $video,
            'fuente_disponible' => (bool)($fuente['disponible'] ?? false),
            'motivo' => (string)($fuente['motivo'] ?? ''),
            'duracion_segundos' => (float)($fuente['duracion'] ?? 0),
            'segmentos' => (int)($fuente['segmentos'] ?? 0),
            'fuente_hash' => $hash,
            'borrador' => $borradorCompleto,
            'publicada' => $publicadaCompleta,
            'borrador_desactualizado' => $borrador && $hash !== '' && !hash_equals((string)$borrador['fuente_hash'], $hash),
            'publicada_desactualizada' => $publicada && $hash !== '' && !hash_equals((string)$publicada['fuente_hash'], $hash),
        ];
    }

    public function generar(int $idVideo, int $idUsuario): array
    {
        if (!$this->model->tablasDisponibles()) {
            throw new RuntimeException('Primero importa database/migracion_v4_4_3_capitulos_inteligentes.sql.');
        }

        $fuente = $this->prepararFuente($idVideo, true);
        if (empty($fuente['disponible'])) {
            throw new RuntimeException((string)($fuente['motivo'] ?? 'No hay una transcripción disponible para generar capítulos.'));
        }

        $ai = new AIManager();
        $resultado = $ai->generarConFallback(
            $this->crearMensajes($fuente),
            ['groq', 'gemini'],
            [
                'temperature' => 0.15,
                'max_tokens' => 2600,
            ]
        );

        if (empty($resultado['ok'])) {
            throw new RuntimeException((string)($resultado['mensaje'] ?? 'DEVIOZ AI no pudo proponer los capítulos.'));
        }

        $capitulos = $this->parsearRespuesta(
            (string)($resultado['respuesta'] ?? ''),
            $fuente['segmentos_raw'],
            (float)$fuente['duracion']
        );

        return $this->model->crearBorrador(
            $idVideo,
            (string)$fuente['hash'],
            (int)$fuente['segmentos'],
            (float)$fuente['duracion'],
            $capitulos,
            isset($resultado['provider']) ? (string)$resultado['provider'] : null,
            isset($resultado['model']) ? (string)$resultado['model'] : null,
            $idUsuario
        );
    }

    public function guardarBorrador(int $idVideo, int $idGeneracion, array $filas): array
    {
        if (!$this->model->tablasDisponibles()) {
            throw new RuntimeException('La migración V4.4.3 todavía no está instalada.');
        }

        $generacion = $this->model->obtenerGeneracion($idGeneracion);
        if (!$generacion || (int)$generacion['id_video'] !== $idVideo || ($generacion['estado'] ?? '') !== 'borrador') {
            throw new RuntimeException('El borrador indicado no pertenece a este video o ya no puede editarse.');
        }

        $video = $this->model->video($idVideo);
        $duracion = max(
            0.0,
            (float)($generacion['duracion_segundos'] ?? 0),
            (float)($video['duracion_segundos'] ?? 0)
        );

        $limpios = [];
        foreach ($filas as $fila) {
            $titulo = trim((string)($fila['titulo'] ?? ''));
            if ($titulo === '') {
                continue;
            }
            $inicio = $this->parsearTiempo($fila['inicio'] ?? 0);
            if ($duracion > 0) {
                $inicio = min($duracion, $inicio);
            }
            $limpios[] = [
                'inicio_segundos' => max(0, $inicio),
                'titulo' => mb_substr($titulo, 0, 180, 'UTF-8'),
                'resumen' => mb_substr(trim((string)($fila['resumen'] ?? '')), 0, 2000, 'UTF-8'),
                'conceptos' => $this->limpiarConceptos($fila['conceptos'] ?? []),
            ];
        }

        if (!$limpios) {
            throw new RuntimeException('Agrega al menos un capítulo antes de guardar.');
        }

        usort($limpios, static fn(array $a, array $b): int => $a['inicio_segundos'] <=> $b['inicio_segundos']);
        $sinDuplicados = [];
        $vistos = [];
        foreach ($limpios as $capitulo) {
            $clave = number_format((float)$capitulo['inicio_segundos'], 3, '.', '');
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            $sinDuplicados[] = $capitulo;
        }

        $capitulos = $this->calcularFinales($sinDuplicados, $duracion);
        return $this->model->reemplazarCapitulosBorrador($idGeneracion, $capitulos);
    }

    public function publicar(int $idVideo, int $idGeneracion, int $idUsuario): void
    {
        $generacion = $this->model->obtenerGeneracion($idGeneracion);
        if (!$generacion || (int)$generacion['id_video'] !== $idVideo) {
            throw new RuntimeException('La propuesta indicada no pertenece a este video.');
        }
        $this->model->publicar($idGeneracion, $idUsuario);
    }

    public function descartar(int $idVideo, int $idGeneracion): void
    {
        $generacion = $this->model->obtenerGeneracion($idGeneracion);
        if (!$generacion || (int)$generacion['id_video'] !== $idVideo) {
            throw new RuntimeException('El borrador indicado no pertenece a este video.');
        }
        $this->model->descartarBorrador($idGeneracion);
    }

    private function prepararFuente(int $idVideo, bool $incluirTexto): array
    {
        $video = $this->model->video($idVideo);
        if (!$video) {
            return ['disponible' => false, 'motivo' => 'El video no existe.'];
        }

        if (($video['transcripcion_estado'] ?? '') !== 'completada') {
            return [
                'disponible' => false,
                'motivo' => 'El video necesita una transcripción completada antes de generar capítulos.',
                'video' => $video,
                'hash' => '',
                'duracion' => (float)($video['duracion_segundos'] ?? 0),
                'segmentos' => 0,
                'segmentos_raw' => [],
                'texto' => '',
            ];
        }

        $segmentos = $this->model->segmentosVideo($idVideo);
        if (!$segmentos) {
            return [
                'disponible' => false,
                'motivo' => 'La transcripción está marcada como completada, pero no contiene segmentos.',
                'video' => $video,
                'hash' => '',
                'duracion' => (float)($video['duracion_segundos'] ?? 0),
                'segmentos' => 0,
                'segmentos_raw' => [],
                'texto' => '',
            ];
        }

        $ultimo = end($segmentos);
        reset($segmentos);
        $duracion = max(
            (float)($video['duracion_segundos'] ?? 0),
            (float)($ultimo['fin_segundos'] ?? 0),
            (float)($ultimo['inicio_segundos'] ?? 0)
        );

        $hashCtx = hash_init('sha256');
        hash_update($hashCtx, 'video|' . $idVideo . '|');
        foreach ($segmentos as $segmento) {
            hash_update($hashCtx, implode('|', [
                (string)($segmento['inicio_segundos'] ?? 0),
                (string)($segmento['fin_segundos'] ?? 0),
                preg_replace('/\s+/u', ' ', trim((string)($segmento['texto'] ?? ''))) ?: '',
            ]) . "\n");
        }
        $hash = hash_final($hashCtx);

        return [
            'disponible' => true,
            'motivo' => '',
            'video' => $video,
            'hash' => $hash,
            'duracion' => $duracion,
            'segmentos' => count($segmentos),
            'segmentos_raw' => $segmentos,
            'texto' => $incluirTexto ? $this->construirFuenteTemporal($segmentos, 36000) : '',
        ];
    }

    private function construirFuenteTemporal(array $segmentos, int $maxCaracteres): string
    {
        $lineas = [];
        $caracteres = 0;

        foreach ($segmentos as $seg) {
            $texto = preg_replace('/\s+/u', ' ', trim((string)($seg['texto'] ?? ''))) ?: '';
            if ($texto === '') {
                continue;
            }
            $linea = '[' . $this->formatearTiempo((float)$seg['inicio_segundos']) . '] ' . $texto;
            $lineas[] = $linea;
            $caracteres += mb_strlen($linea, 'UTF-8') + 1;
        }

        if ($caracteres <= $maxCaracteres) {
            return implode("\n", $lineas);
        }

        // Para videos largos conservamos cobertura de inicio a fin. Se agrupan
        // segmentos contiguos en ventanas temporales, evitando truncar solo el final.
        $total = count($segmentos);
        $ventanas = min(72, max(24, (int)ceil($maxCaracteres / 430)));
        $tamano = max(1, (int)ceil($total / $ventanas));
        $salida = [];
        $usados = 0;

        for ($i = 0; $i < $total; $i += $tamano) {
            $grupo = array_slice($segmentos, $i, $tamano);
            if (!$grupo) {
                continue;
            }
            $textos = [];
            foreach ($grupo as $seg) {
                $texto = preg_replace('/\s+/u', ' ', trim((string)($seg['texto'] ?? ''))) ?: '';
                if ($texto !== '') {
                    $textos[] = $texto;
                }
            }
            if (!$textos) {
                continue;
            }
            $combinado = implode(' ', $textos);
            if (mb_strlen($combinado, 'UTF-8') > 420) {
                $combinado = mb_substr($combinado, 0, 300, 'UTF-8') . ' … ' . mb_substr($combinado, -100, null, 'UTF-8');
            }
            $primero = $grupo[0];
            $ultimo = $grupo[count($grupo) - 1];
            $linea = '[' . $this->formatearTiempo((float)$primero['inicio_segundos']) . '-' . $this->formatearTiempo((float)$ultimo['fin_segundos']) . '] ' . $combinado;
            $largo = mb_strlen($linea, 'UTF-8') + 1;
            if ($usados + $largo > $maxCaracteres && $salida) {
                break;
            }
            $salida[] = $linea;
            $usados += $largo;
        }

        return implode("\n", $salida);
    }

    private function crearMensajes(array $fuente): array
    {
        $video = $fuente['video'];
        $duracion = (float)$fuente['duracion'];
        [$minCap, $maxCap] = $this->rangoCapitulos($duracion);
        $primero = $fuente['segmentos_raw'][0] ?? null;
        $inicioReal = $primero ? $this->formatearTiempo((float)$primero['inicio_segundos']) : '00:00';

        $system = <<<TXT
Eres DEVIOZ AI y debes proponer capítulos educativos para un video usando EXCLUSIVAMENTE la transcripción con marcas de tiempo entregada. No inventes temas, tecnologías ni tiempos fuera de la fuente.

Devuelve SOLO JSON válido, sin markdown ni texto adicional, con esta estructura exacta:
{"capitulos":[{"inicio":"00:00","titulo":"Título breve","resumen":"Qué se aprende en esta escena","conceptos":["concepto 1","concepto 2"]}]}

Reglas obligatorias:
- Propón entre {$minCap} y {$maxCap} capítulos, salvo que el contenido realmente no permita tantos.
- Los capítulos deben seguir el orden temporal y cubrir el video de inicio a fin.
- El primer capítulo debe comenzar cerca del primer timestamp real ({$inicioReal}).
- Usa como inicio timestamps que aparezcan en la fuente o estén claramente dentro de una ventana temporal mostrada.
- No crees capítulos cada pocos segundos: agrupa fragmentos que pertenecen al mismo tema.
- Títulos de máximo 80 caracteres.
- Resúmenes de 1 o 2 frases, máximo 320 caracteres.
- Máximo 5 conceptos por capítulo. Si no hay conceptos claros, usa [].
TXT;

        $meta = array_filter([
            (string)($video['categoria'] ?? ''),
            (string)($video['serie'] ?? ''),
            !empty($video['numero_temporada']) ? 'T' . (int)$video['numero_temporada'] : '',
            !empty($video['numero_capitulo']) ? 'C' . (int)$video['numero_capitulo'] : '',
        ]);

        $user = 'VIDEO: ' . (string)$video['titulo'] . "\n";
        if ($meta) {
            $user .= 'CONTEXTO: ' . implode(' · ', $meta) . "\n";
        }
        $user .= 'DURACIÓN APROXIMADA: ' . $this->formatearTiempo($duracion) . "\n";
        if (!empty($video['descripcion'])) {
            $user .= 'DESCRIPCIÓN: ' . trim((string)$video['descripcion']) . "\n";
        }
        $user .= "\nTRANSCRIPCIÓN TEMPORAL:\n" . $fuente['texto'];

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ];
    }

    private function parsearRespuesta(string $texto, array $segmentos, float $duracion): array
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

        $items = is_array($datos) ? ($datos['capitulos'] ?? null) : null;
        if (!is_array($items) || !$items) {
            throw new RuntimeException('La IA devolvió un formato de capítulos inválido. Intenta nuevamente.');
        }

        $startsReales = array_values(array_map(static fn(array $seg): float => (float)$seg['inicio_segundos'], $segmentos));
        $primeroReal = $startsReales[0] ?? 0.0;
        $capitulos = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $titulo = trim((string)($item['titulo'] ?? ''));
            if ($titulo === '') {
                continue;
            }
            $inicioPropuesto = $this->parsearTiempo($item['inicio_segundos'] ?? ($item['inicio'] ?? 0));
            $inicioAjustado = $this->snapInicio($inicioPropuesto, $startsReales);
            $capitulos[] = [
                'inicio_segundos' => max(0, min($duracion > 0 ? $duracion : PHP_FLOAT_MAX, $inicioAjustado)),
                'titulo' => mb_substr($titulo, 0, 180, 'UTF-8'),
                'resumen' => mb_substr(trim((string)($item['resumen'] ?? '')), 0, 2000, 'UTF-8'),
                'conceptos' => $this->limpiarConceptos($item['conceptos'] ?? []),
            ];
            if (count($capitulos) >= 14) {
                break;
            }
        }

        if (!$capitulos) {
            throw new RuntimeException('DEVIOZ AI no propuso capítulos utilizables.');
        }

        usort($capitulos, static fn(array $a, array $b): int => $a['inicio_segundos'] <=> $b['inicio_segundos']);
        if ($capitulos[0]['inicio_segundos'] > $primeroReal + 18) {
            $capitulos[0]['inicio_segundos'] = $primeroReal;
        }

        $sinDuplicados = [];
        $vistos = [];
        foreach ($capitulos as $capitulo) {
            $clave = number_format((float)$capitulo['inicio_segundos'], 3, '.', '');
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            $sinDuplicados[] = $capitulo;
        }

        if (count($sinDuplicados) < 2 && $duracion >= 180) {
            throw new RuntimeException('La IA propuso muy pocos capítulos para la duración del video. Intenta regenerarlos.');
        }

        return $this->calcularFinales($sinDuplicados, $duracion);
    }

    private function calcularFinales(array $capitulos, float $duracion): array
    {
        $capitulos = array_values($capitulos);
        $total = count($capitulos);
        for ($i = 0; $i < $total; $i++) {
            $inicio = (float)$capitulos[$i]['inicio_segundos'];
            $siguiente = $i + 1 < $total ? (float)$capitulos[$i + 1]['inicio_segundos'] : $duracion;
            if ($siguiente <= $inicio) {
                $siguiente = $duracion > $inicio ? $duracion : $inicio + 1;
            }
            $capitulos[$i]['fin_segundos'] = max($inicio, $siguiente);
        }
        return $capitulos;
    }

    private function snapInicio(float $inicio, array $starts): float
    {
        if (!$starts) {
            return max(0, $inicio);
        }
        $mejor = (float)$starts[0];
        $distancia = abs($mejor - $inicio);
        foreach ($starts as $start) {
            $d = abs((float)$start - $inicio);
            if ($d < $distancia) {
                $mejor = (float)$start;
                $distancia = $d;
            }
        }
        return $mejor;
    }

    private function limpiarConceptos(mixed $valor): array
    {
        if (is_string($valor)) {
            $valor = preg_split('/[,;\n]+/u', $valor) ?: [];
        }
        if (!is_array($valor)) {
            return [];
        }
        $salida = [];
        foreach ($valor as $item) {
            $item = trim((string)$item);
            if ($item === '') {
                continue;
            }
            $salida[] = mb_substr($item, 0, 80, 'UTF-8');
            if (count($salida) >= 6) {
                break;
            }
        }
        return array_values(array_unique($salida));
    }

    private function parsearTiempo(mixed $valor): float
    {
        if (is_int($valor) || is_float($valor) || (is_string($valor) && is_numeric(trim($valor)))) {
            return max(0, (float)$valor);
        }

        $texto = trim((string)$valor);
        if ($texto === '') {
            return 0.0;
        }
        $partes = array_map('trim', explode(':', $texto));
        if (count($partes) === 2) {
            return max(0, ((int)$partes[0] * 60) + (float)$partes[1]);
        }
        if (count($partes) === 3) {
            return max(0, ((int)$partes[0] * 3600) + ((int)$partes[1] * 60) + (float)$partes[2]);
        }
        return 0.0;
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

    private function rangoCapitulos(float $duracion): array
    {
        if ($duracion < 180) {
            return [1, 4];
        }
        if ($duracion < 600) {
            return [3, 6];
        }
        if ($duracion < 1200) {
            return [4, 8];
        }
        if ($duracion < 2400) {
            return [6, 10];
        }
        return [8, 12];
    }
}
