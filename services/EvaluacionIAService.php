<?php

require_once __DIR__ . '/../models/EvaluacionIA.php';
require_once __DIR__ . '/../AIManager/AIManager.php';

class EvaluacionIAService
{
    private EvaluacionIA $model;

    public function __construct()
    {
        $this->model = new EvaluacionIA();
    }

    public function panel(int $idCurso): array
    {
        $curso = $this->model->curso($idCurso);
        if (!$curso) throw new InvalidArgumentException('El curso no existe.');

        $lecciones = $this->model->leccionesCurso($idCurso);
        $transcritas = 0;
        foreach ($lecciones as &$leccion) {
            $leccion['transcrita'] = ($leccion['transcripcion_estado'] ?? '') === 'completada';
            if ($leccion['transcrita']) $transcritas++;
            $leccion['titulo_mostrar'] = trim((string)($leccion['titulo_personalizado'] ?: $leccion['video_titulo']));
        }
        unset($leccion);

        return [
            'tabla_disponible'=>$this->model->tablaDisponible(),
            'curso'=>$curso,
            'lecciones'=>$lecciones,
            'cobertura'=>['transcritas'=>$transcritas,'total'=>count($lecciones)],
            'borrador'=>$this->model->tablaDisponible() ? $this->model->ultimoBorradorCurso($idCurso) : null,
        ];
    }

    public function generar(int $idCurso, array $entrada, int $idUsuario): array
    {
        if (!$this->model->tablaDisponible()) {
            throw new RuntimeException('Primero importa database/migracion_v4_4_2_evaluaciones_ia.sql.');
        }

        $cantidad = max(3, min(20, (int)($entrada['cantidad'] ?? 10)));
        $dificultad = $this->normalizarDificultad((string)($entrada['dificultad'] ?? 'intermedia'));
        $tipos = $entrada['tipos'] ?? ['opcion_multiple','verdadero_falso'];
        if (!is_array($tipos)) $tipos = [];
        $tipos = array_values(array_unique(array_filter(array_map('strval', $tipos), static fn(string $t): bool => in_array($t,['opcion_multiple','verdadero_falso'],true))));
        if (!$tipos) throw new InvalidArgumentException('Selecciona al menos un tipo de pregunta.');

        $alcance = (($entrada['alcance'] ?? 'curso') === 'lecciones') ? 'lecciones' : 'curso';
        $leccionesIds = $entrada['lecciones_ids'] ?? [];
        if (!is_array($leccionesIds)) $leccionesIds = [];
        $leccionesIds = array_values(array_unique(array_filter(array_map('intval',$leccionesIds), static fn(int $id): bool => $id > 0)));
        if ($alcance === 'lecciones' && !$leccionesIds) {
            throw new InvalidArgumentException('Selecciona al menos una lección transcrita.');
        }

        $fuente = $this->prepararFuente($idCurso, $alcance, $leccionesIds);
        $existentes = $this->model->preguntasExistentesCurso($idCurso);
        $mensajes = $this->crearMensajes($fuente, $cantidad, $dificultad, $tipos, $existentes);

        $ai = new AIManager();
        $resultado = $ai->generarConFallback($mensajes, ['groq','gemini'], [
            'temperature'=>0.35,
            'max_tokens'=>5200,
        ]);
        if (empty($resultado['ok'])) {
            throw new RuntimeException((string)($resultado['mensaje'] ?? 'DEVIOZ AI no pudo generar la evaluación.'));
        }

        $preguntas = $this->parsearPreguntas((string)($resultado['respuesta'] ?? ''), $tipos, $dificultad);
        $preguntas = $this->filtrarDuplicadas($preguntas, $existentes);
        if (!$preguntas) {
            throw new RuntimeException('La IA no devolvió preguntas nuevas válidas. Prueba otro alcance o vuelve a generar.');
        }
        if (count($preguntas) > $cantidad) $preguntas = array_slice($preguntas,0,$cantidad);

        $config = [
            'cantidad'=>$cantidad,
            'dificultad'=>$dificultad,
            'tipos'=>$tipos,
            'alcance'=>$alcance,
            'lecciones_ids'=>$fuente['lecciones_ids'],
        ];
        $meta = [
            'fuente_hash'=>$fuente['hash'],
            'fuente_items'=>$fuente['items'],
            'proveedor'=>$resultado['provider'] ?? null,
            'modelo'=>$resultado['model'] ?? null,
        ];
        $borrador = $this->model->crearBorrador($idCurso,$config,$preguntas,$meta,$idUsuario);

        $advertencia = '';
        if (count($preguntas) < $cantidad) {
            $advertencia = 'Se conservaron '.count($preguntas).' de '.$cantidad.' preguntas porque se descartaron respuestas inválidas o demasiado parecidas a preguntas existentes.';
        }
        if ($fuente['cobertura']['transcritas'] < $fuente['cobertura']['seleccionadas']) {
            $extra = ' Algunas lecciones seleccionadas no tenían una transcripción completada y no se utilizaron.';
            $advertencia = trim($advertencia.' '.$extra);
        }

        return [
            'ok'=>true,
            'borrador'=>$borrador,
            'generadas'=>count($preguntas),
            'solicitadas'=>$cantidad,
            'cobertura'=>$fuente['cobertura'],
            'advertencia'=>$advertencia,
        ];
    }

    public function actualizarPregunta(int $idCurso, int $idPregunta, array $datos): void
    {
        $this->model->actualizarPreguntaBorrador($idPregunta,$idCurso,$datos);
    }

    public function eliminarPregunta(int $idCurso, int $idPregunta): void
    {
        $this->model->eliminarPreguntaBorrador($idPregunta,$idCurso);
    }

    public function descartar(int $idCurso, int $idBorrador): void
    {
        $this->model->descartarBorrador($idBorrador,$idCurso);
    }

    public function aprobar(int $idCurso, int $idBorrador, int $idUsuario): array
    {
        return $this->model->aprobarBorrador($idBorrador,$idCurso,$idUsuario);
    }

    private function prepararFuente(int $idCurso, string $alcance, array $seleccionadas): array
    {
        $curso = $this->model->curso($idCurso);
        if (!$curso) throw new InvalidArgumentException('El curso no existe.');
        $lecciones = $this->model->leccionesCurso($idCurso);
        if (!$lecciones) throw new RuntimeException('El curso todavía no tiene lecciones.');

        $mapSeleccion = array_fill_keys($seleccionadas,true);
        $candidatas = [];
        foreach ($lecciones as $l) {
            if ($alcance === 'lecciones' && !isset($mapSeleccion[(int)$l['id_leccion']])) continue;
            $candidatas[] = $l;
        }
        if (!$candidatas) throw new RuntimeException('No se encontraron lecciones válidas para el alcance seleccionado.');

        $transcritas = array_values(array_filter($candidatas, static fn(array $l): bool => ($l['transcripcion_estado'] ?? '') === 'completada'));
        if (!$transcritas) throw new RuntimeException('Las lecciones seleccionadas necesitan una transcripción completada antes de generar preguntas.');

        $maxTotal = 34000;
        $porLeccion = max(2200, min(6500, (int)floor($maxTotal / max(1,count($transcritas)))));
        $bloques = [];
        $hashPartes = ['v4.4.2',$idCurso,(string)($curso['fecha_actualizacion'] ?? ''),$alcance];
        $idsUsadas = [];

        foreach ($transcritas as $l) {
            $segmentos = $this->model->segmentosVideo((int)$l['id_video']);
            $extracto = $this->muestrearSegmentos($segmentos,$porLeccion);
            if ($extracto === '') continue;
            $titulo = trim((string)($l['titulo_personalizado'] ?: $l['video_titulo']));
            $bloques[] = "LECCIÓN ".(int)$l['orden']." - {$titulo}\n{$extracto}";
            $idsUsadas[] = (int)$l['id_leccion'];
            $hashPartes[] = implode(':',[(int)$l['id_leccion'],(int)$l['id_video'],(string)$l['transcripcion_actualizacion']]);
        }
        if (!$bloques) throw new RuntimeException('No se encontró texto transcrito suficiente para generar la evaluación.');

        return [
            'curso'=>$curso,
            'texto'=>implode("\n\n",$bloques),
            'items'=>count($idsUsadas),
            'lecciones_ids'=>$idsUsadas,
            'hash'=>hash('sha256',implode('|',$hashPartes)),
            'cobertura'=>[
                'transcritas'=>count($idsUsadas),
                'seleccionadas'=>count($candidatas),
                'total'=>count($lecciones),
            ],
        ];
    }

    private function muestrearSegmentos(array $segmentos, int $maxCaracteres): string
    {
        $segmentos = array_values(array_filter($segmentos, static fn(array $s): bool => trim((string)($s['texto'] ?? '')) !== ''));
        if (!$segmentos) return '';

        $objetivo = min(count($segmentos),54);
        $indices = [];
        if (count($segmentos) <= $objetivo) {
            $indices = range(0,count($segmentos)-1);
        } else {
            for ($i=0;$i<$objetivo;$i++) {
                $indices[] = (int)round($i*(count($segmentos)-1)/max(1,$objetivo-1));
            }
            $indices = array_values(array_unique($indices));
        }

        $salida = [];
        $chars = 0;
        foreach ($indices as $idx) {
            $seg = $segmentos[$idx];
            $texto = preg_replace('/\s+/u',' ',trim((string)$seg['texto'])) ?: '';
            if ($texto === '') continue;
            $linea = '['.$this->formatearTiempo((float)($seg['inicio_segundos'] ?? 0)).'] '.$texto;
            $largo = mb_strlen($linea,'UTF-8')+1;
            if ($chars+$largo > $maxCaracteres && $salida) break;
            $salida[] = $linea;
            $chars += $largo;
        }
        return implode("\n",$salida);
    }

    private function crearMensajes(array $fuente, int $cantidad, string $dificultad, array $tipos, array $existentes): array
    {
        $tiposTexto = [];
        if (in_array('opcion_multiple',$tipos,true)) $tiposTexto[] = 'opcion_multiple';
        if (in_array('verdadero_falso',$tipos,true)) $tiposTexto[] = 'verdadero_falso';

        $system = <<<TXT
Eres DEVIOZ AI actuando como diseñador de evaluaciones educativas. Debes crear preguntas usando EXCLUSIVAMENTE la fuente transcrita entregada. No uses conocimientos externos, no inventes datos y no preguntes por información que no esté respaldada por la fuente.

Devuelve SOLO JSON válido UTF-8, sin markdown ni bloques ```. Estructura exacta:
{"preguntas":[{"pregunta":"texto","tipo":"opcion_multiple","dificultad":"intermedia","puntos":1,"explicacion":"por qué la respuesta es correcta según la fuente","opciones":[{"texto":"A","correcta":false},{"texto":"B","correcta":true},{"texto":"C","correcta":false},{"texto":"D","correcta":false}]}]}

Reglas obligatorias:
- Genera {$cantidad} preguntas distintas, dificultad {$dificultad}.
- Tipos permitidos: %TIPOS%.
- Para opcion_multiple crea exactamente 4 alternativas plausibles y exactamente 1 correcta.
- Para verdadero_falso usa exactamente dos opciones: Verdadero y Falso, con exactamente 1 correcta.
- Evita preguntas ambiguas, de memoria trivial, dobles negaciones y respuestas evidentes por longitud.
- Distribuye las preguntas entre distintos conceptos y lecciones cuando la fuente lo permita.
- La explicación debe ser breve y respaldada por la transcripción.
- No repitas ni reformules demasiado cerca preguntas existentes que se indiquen abajo.
TXT;
        $system = str_replace('%TIPOS%',implode(', ',$tiposTexto),$system);

        $user = "CURSO: ".(string)$fuente['curso']['titulo']."\n";
        if (!empty($fuente['curso']['descripcion'])) $user .= "DESCRIPCIÓN: ".(string)$fuente['curso']['descripcion']."\n";
        $user .= "NIVEL DEL CURSO: ".(string)$fuente['curso']['nivel']."\n";
        if ($existentes) {
            $user .= "\nPREGUNTAS QUE YA EXISTEN Y NO DEBES REPETIR:\n";
            foreach (array_slice($existentes,-40) as $i=>$q) $user .= ($i+1).'. '.$q."\n";
        }
        $user .= "\nFUENTE TRANSCRITA:\n".$fuente['texto'];

        return [
            ['role'=>'system','content'=>$system],
            ['role'=>'user','content'=>$user],
        ];
    }

    private function parsearPreguntas(string $texto, array $tiposPermitidos, string $dificultadDefecto): array
    {
        $texto = trim($texto);
        $texto = preg_replace('/^```(?:json)?\s*/i','',$texto) ?? $texto;
        $texto = preg_replace('/\s*```$/','',$texto) ?? $texto;
        $datos = json_decode($texto,true);
        if (!is_array($datos)) {
            $inicio = strpos($texto,'{');
            $fin = strrpos($texto,'}');
            if ($inicio !== false && $fin !== false && $fin > $inicio) {
                $datos = json_decode(substr($texto,$inicio,$fin-$inicio+1),true);
            }
        }
        if (!is_array($datos) || !isset($datos['preguntas']) || !is_array($datos['preguntas'])) {
            throw new RuntimeException('La IA devolvió un formato de evaluación inválido. Intenta nuevamente.');
        }

        $salida = [];
        foreach ($datos['preguntas'] as $p) {
            if (!is_array($p)) continue;
            $pregunta = mb_substr(trim((string)($p['pregunta'] ?? '')),0,800,'UTF-8');
            if ($pregunta === '') continue;
            $tipo = (string)($p['tipo'] ?? '');
            if (!in_array($tipo,$tiposPermitidos,true)) continue;
            $dificultad = $this->normalizarDificultad((string)($p['dificultad'] ?? $dificultadDefecto));
            $explicacion = mb_substr(trim((string)($p['explicacion'] ?? '')),0,1500,'UTF-8');
            $rawOpciones = $p['opciones'] ?? [];
            if (!is_array($rawOpciones)) continue;

            $opciones = [];
            foreach ($rawOpciones as $o) {
                if (!is_array($o)) continue;
                $txt = mb_substr(trim((string)($o['texto'] ?? '')),0,500,'UTF-8');
                if ($txt === '') continue;
                $opciones[] = ['texto'=>$txt,'correcta'=>!empty($o['correcta'])];
            }

            if ($tipo === 'verdadero_falso') {
                if (count($opciones) !== 2 || count(array_filter($opciones,static fn(array $o): bool=>$o['correcta'])) !== 1) continue;
                $correctaVerdadero = null;
                foreach ($opciones as $op) {
                    if (empty($op['correcta'])) continue;
                    $etiqueta = $this->normalizarTexto((string)$op['texto']);
                    if (in_array($etiqueta,['verdadero','true'],true)) $correctaVerdadero = true;
                    if (in_array($etiqueta,['falso','false'],true)) $correctaVerdadero = false;
                }
                if ($correctaVerdadero === null) continue;
                $opciones = [
                    ['texto'=>'Verdadero','correcta'=>$correctaVerdadero],
                    ['texto'=>'Falso','correcta'=>!$correctaVerdadero],
                ];
            } else {
                if (count($opciones) !== 4 || count(array_filter($opciones,static fn(array $o): bool=>$o['correcta'])) !== 1) continue;
            }

            $salida[] = [
                'pregunta'=>$pregunta,
                'tipo'=>$tipo,
                'dificultad'=>$dificultad,
                'puntos'=>max(.1,min(100,(float)($p['puntos'] ?? 1))),
                'explicacion'=>$explicacion,
                'opciones'=>$opciones,
            ];
        }
        return $salida;
    }

    private function filtrarDuplicadas(array $preguntas, array $existentes): array
    {
        $bases = [];
        foreach ($existentes as $q) {
            $n = $this->normalizarTexto($q);
            if ($n !== '') $bases[] = $n;
        }
        $salida = [];
        foreach ($preguntas as $p) {
            $n = $this->normalizarTexto((string)$p['pregunta']);
            if ($n === '') continue;
            $duplicada = false;
            foreach ($bases as $b) {
                if ($n === $b) { $duplicada=true; break; }
                similar_text($n,$b,$pct);
                if ($pct >= 88.0) { $duplicada=true; break; }
            }
            if ($duplicada) continue;
            $salida[] = $p;
            $bases[] = $n;
        }
        return $salida;
    }

    private function normalizarTexto(string $texto): string
    {
        $texto = mb_strtolower(trim($texto),'UTF-8');
        $texto = preg_replace('/[^\p{L}\p{N}\s]+/u',' ',$texto) ?? $texto;
        $texto = preg_replace('/\s+/u',' ',$texto) ?? $texto;
        return trim($texto);
    }

    private function normalizarDificultad(string $dificultad): string
    {
        $dificultad = strtolower(trim($dificultad));
        return in_array($dificultad,['basica','intermedia','avanzada'],true) ? $dificultad : 'intermedia';
    }

    private function formatearTiempo(float $segundos): string
    {
        $total = max(0,(int)round($segundos));
        $h = intdiv($total,3600);
        $resto = $total % 3600;
        $m = intdiv($resto,60);
        $s = $resto % 60;
        return $h > 0 ? sprintf('%02d:%02d:%02d',$h,$m,$s) : sprintf('%02d:%02d',$m,$s);
    }
}
