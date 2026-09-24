<?php

require_once __DIR__ . '/../config/conexion.php';

class Skill
{
    private PDO $conexion;

    public function __construct()
    {
        $db = new Conexion();
        $this->conexion = $db->conectar();
    }

    private function texto($valor, int $max = 0): string
    {
        $valor = trim((string)$valor);
        if ($max > 0) {
            return function_exists('mb_substr')
                ? mb_substr($valor, 0, $max, 'UTF-8')
                : substr($valor, 0, $max);
        }
        return $valor;
    }

    private function generarCodigo(string $texto): string
    {
        $normalizado = $texto;
        if (function_exists('iconv')) {
            $convertido = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalizado);
            if (is_string($convertido) && $convertido !== '') {
                $normalizado = $convertido;
            }
        }
        $normalizado = strtoupper($normalizado);
        $normalizado = preg_replace('/[^A-Z0-9]+/', '_', $normalizado) ?? '';
        $normalizado = trim($normalizado, '_');
        return substr($normalizado !== '' ? $normalizado : 'SKILL', 0, 60);
    }

    public function listar(string $buscar = '', string $categoria = '', string $estado = ''): array
    {
        $sql = "
            SELECT s.*,
                   COUNT(DISTINCT cs.id_curso) AS cursos_asociados
            FROM learning_skills s
            LEFT JOIN learning_curso_skills cs ON cs.id_skill = s.id_skill
            WHERE 1=1
        ";
        $params = [];

        if ($buscar !== '') {
            $sql .= " AND (s.nombre LIKE :buscar OR s.codigo LIKE :buscar2 OR s.descripcion LIKE :buscar3)";
            $params[':buscar'] = '%' . $buscar . '%';
            $params[':buscar2'] = '%' . $buscar . '%';
            $params[':buscar3'] = '%' . $buscar . '%';
        }

        if ($categoria !== '') {
            $sql .= " AND s.categoria = :categoria";
            $params[':categoria'] = $categoria;
        }

        if ($estado === '1' || $estado === '0') {
            $sql .= " AND s.estado = :estado";
            $params[':estado'] = (int)$estado;
        }

        $sql .= " GROUP BY s.id_skill ORDER BY s.estado DESC, s.categoria ASC, s.nombre ASC";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function catalogoActivo(): array
    {
        $stmt = $this->conexion->query("SELECT * FROM learning_skills WHERE estado=1 ORDER BY categoria,nombre");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function categorias(): array
    {
        $stmt = $this->conexion->query("SELECT DISTINCT categoria FROM learning_skills WHERE categoria <> '' ORDER BY categoria ASC");
        return array_values(array_filter(array_map(static fn($r) => (string)$r['categoria'], $stmt->fetchAll(PDO::FETCH_ASSOC))));
    }

    public function buscar(int $idSkill): ?array
    {
        $stmt = $this->conexion->prepare("SELECT * FROM learning_skills WHERE id_skill = :id LIMIT 1");
        $stmt->execute([':id' => $idSkill]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function guardar(array $datos): int
    {
        $id = (int)($datos['id_skill'] ?? 0);
        $nombre = $this->texto($datos['nombre'] ?? '', 120);
        if ($nombre === '') {
            throw new InvalidArgumentException('El nombre de la skill es obligatorio.');
        }

        $codigo = $this->texto($datos['codigo'] ?? '', 60);
        $codigo = $codigo !== '' ? $this->generarCodigo($codigo) : $this->generarCodigo($nombre);
        $categoria = $this->texto($datos['categoria'] ?? 'General', 80);
        if ($categoria === '') {
            $categoria = 'General';
        }
        $descripcion = $this->texto($datos['descripcion'] ?? '', 500);
        $icono = $this->texto($datos['icono'] ?? '🧩', 20);
        if ($icono === '') {
            $icono = '🧩';
        }
        $estado = !empty($datos['estado']) ? 1 : 0;

        try {
            if ($id > 0) {
                $stmt = $this->conexion->prepare("UPDATE learning_skills SET codigo=:codigo,nombre=:nombre,categoria=:categoria,descripcion=:descripcion,icono=:icono,estado=:estado WHERE id_skill=:id");
                $stmt->execute([
                    ':codigo'=>$codigo,
                    ':nombre'=>$nombre,
                    ':categoria'=>$categoria,
                    ':descripcion'=>$descripcion,
                    ':icono'=>$icono,
                    ':estado'=>$estado,
                    ':id'=>$id,
                ]);
                return $id;
            }

            $stmt = $this->conexion->prepare("INSERT INTO learning_skills (codigo,nombre,categoria,descripcion,icono,estado) VALUES (:codigo,:nombre,:categoria,:descripcion,:icono,:estado)");
            $stmt->execute([
                ':codigo'=>$codigo,
                ':nombre'=>$nombre,
                ':categoria'=>$categoria,
                ':descripcion'=>$descripcion,
                ':icono'=>$icono,
                ':estado'=>$estado,
            ]);
            return (int)$this->conexion->lastInsertId();
        } catch (PDOException $e) {
            if ((string)$e->getCode() === '23000') {
                throw new InvalidArgumentException('Ya existe una skill con ese nombre o código.');
            }
            throw $e;
        }
    }

    public function eliminar(int $idSkill): bool
    {
        $stmt = $this->conexion->prepare("SELECT COUNT(*) FROM learning_curso_skills WHERE id_skill=:id");
        $stmt->execute([':id'=>$idSkill]);
        if ((int)$stmt->fetchColumn() > 0) {
            return false;
        }

        $stmt = $this->conexion->prepare("DELETE FROM learning_skills WHERE id_skill=:id");
        return $stmt->execute([':id'=>$idSkill]);
    }

    public function skillsCurso(int $idCurso): array
    {
        $sql = "
            SELECT s.*,
                   cs.id_curso_skill,
                   cs.peso,
                   cs.nivel_objetivo,
                   cs.origen,
                   cs.confianza_ia,
                   cs.justificacion_ia,
                   cs.proveedor_ia,
                   cs.modelo_ia,
                   cs.fecha_analisis_ia,
                   CASE WHEN cs.id_curso_skill IS NULL THEN 0 ELSE 1 END AS asignada
            FROM learning_skills s
            LEFT JOIN learning_curso_skills cs
                   ON cs.id_skill = s.id_skill
                  AND cs.id_curso = :curso
            WHERE s.estado = 1 OR cs.id_curso_skill IS NOT NULL
            ORDER BY asignada DESC, s.categoria ASC, s.nombre ASC
        ";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute([':curso'=>$idCurso]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function guardarSkillsCurso(int $idCurso, array $ids, array $pesos, array $niveles): void
    {
        $this->guardarAsociaciones($idCurso, $ids, $pesos, $niveles, []);
    }

    public function guardarSkillsCursoIA(int $idCurso, array $sugerencias, string $provider, string $model): void
    {
        if (!$sugerencias) {
            throw new InvalidArgumentException('Selecciona al menos una skill sugerida.');
        }

        $ids = [];
        $pesos = [];
        $niveles = [];
        $metadatos = [];

        foreach ($sugerencias as $sugerencia) {
            if (!is_array($sugerencia) || empty($sugerencia['seleccionada'])) {
                continue;
            }

            $idSkill = (int)($sugerencia['id_skill'] ?? 0);
            if ($idSkill <= 0) {
                $nombre = $this->texto($sugerencia['nombre'] ?? '', 120);
                if ($nombre === '') {
                    continue;
                }

                $idSkill = $this->buscarIdPorNombreOCodigo($nombre);
                if ($idSkill <= 0) {
                    $idSkill = $this->guardar([
                        'nombre' => $nombre,
                        'codigo' => $sugerencia['codigo'] ?? '',
                        'categoria' => $sugerencia['categoria'] ?? 'General',
                        'descripcion' => $sugerencia['descripcion'] ?? '',
                        'icono' => $sugerencia['icono'] ?? '🧩',
                        'estado' => 1,
                    ]);
                }
            }

            $ids[] = $idSkill;
            $pesos[$idSkill] = $sugerencia['peso'] ?? 0;
            $niveles[$idSkill] = $sugerencia['nivel_objetivo'] ?? 'basico';
            $metadatos[$idSkill] = [
                'origen' => 'ia',
                'confianza' => max(0, min(100, (float)($sugerencia['confianza'] ?? 0))),
                'justificacion' => $this->texto($sugerencia['justificacion'] ?? '', 700),
                'provider' => $this->texto($provider, 30),
                'model' => $this->texto($model, 80),
            ];
        }

        if (!$ids) {
            throw new InvalidArgumentException('Selecciona al menos una skill sugerida.');
        }

        $this->guardarAsociaciones($idCurso, $ids, $pesos, $niveles, $metadatos);
    }

    private function guardarAsociaciones(int $idCurso, array $ids, array $pesos, array $niveles, array $metadatos): void
    {
        $stmt = $this->conexion->prepare("SELECT COUNT(*) FROM learning_cursos WHERE id_curso=:id");
        $stmt->execute([':id'=>$idCurso]);
        if ((int)$stmt->fetchColumn() === 0) {
            throw new InvalidArgumentException('El curso indicado no existe.');
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($id) => $id > 0)));
        $asociaciones = [];
        $total = 0.0;

        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $validStmt = $this->conexion->prepare("SELECT id_skill FROM learning_skills WHERE id_skill IN ($placeholders)");
            $validStmt->execute($ids);
            $validos = array_map('intval', $validStmt->fetchAll(PDO::FETCH_COLUMN));
            $validMap = array_fill_keys($validos, true);

            foreach ($ids as $idSkill) {
                if (!isset($validMap[$idSkill])) {
                    continue;
                }
                $peso = round((float)($pesos[$idSkill] ?? 0), 2);
                if ($peso <= 0 || $peso > 100) {
                    throw new InvalidArgumentException('Cada skill seleccionada debe tener un peso mayor a 0 y máximo de 100%.');
                }
                $nivel = (string)($niveles[$idSkill] ?? 'basico');
                if (!in_array($nivel, ['basico','intermedio','avanzado'], true)) {
                    $nivel = 'basico';
                }
                $meta = $metadatos[$idSkill] ?? [];
                $origen = (($meta['origen'] ?? '') === 'ia') ? 'ia' : 'manual';
                $asociaciones[] = [
                    'id_skill'=>$idSkill,
                    'peso'=>$peso,
                    'nivel'=>$nivel,
                    'origen'=>$origen,
                    'confianza'=>$origen === 'ia' ? round((float)($meta['confianza'] ?? 0), 2) : null,
                    'justificacion'=>$origen === 'ia' ? $this->texto($meta['justificacion'] ?? '', 700) : null,
                    'provider'=>$origen === 'ia' ? $this->texto($meta['provider'] ?? '', 30) : null,
                    'model'=>$origen === 'ia' ? $this->texto($meta['model'] ?? '', 80) : null,
                ];
                $total += $peso;
            }
        }

        if ($asociaciones && abs($total - 100.0) > 0.01) {
            throw new InvalidArgumentException('La suma de los pesos de las skills seleccionadas debe ser exactamente 100%. Actualmente suma ' . rtrim(rtrim(number_format($total, 2, '.', ''), '0'), '.') . '%.');
        }

        $this->conexion->beginTransaction();
        try {
            $delete = $this->conexion->prepare("DELETE FROM learning_curso_skills WHERE id_curso=:curso");
            $delete->execute([':curso'=>$idCurso]);

            if ($asociaciones) {
                $insert = $this->conexion->prepare("INSERT INTO learning_curso_skills (id_curso,id_skill,peso,nivel_objetivo,origen,confianza_ia,justificacion_ia,proveedor_ia,modelo_ia,fecha_analisis_ia) VALUES (:curso,:skill,:peso,:nivel,:origen,:confianza,:justificacion,:provider,:model,:fecha_ia)");
                foreach ($asociaciones as $a) {
                    $insert->execute([
                        ':curso'=>$idCurso,
                        ':skill'=>$a['id_skill'],
                        ':peso'=>$a['peso'],
                        ':nivel'=>$a['nivel'],
                        ':origen'=>$a['origen'],
                        ':confianza'=>$a['confianza'],
                        ':justificacion'=>$a['justificacion'],
                        ':provider'=>$a['provider'],
                        ':model'=>$a['model'],
                        ':fecha_ia'=>$a['origen'] === 'ia' ? date('Y-m-d H:i:s') : null,
                    ]);
                }
            }

            $this->conexion->commit();
        } catch (Throwable $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Calcula el perfil tecnologico del usuario a partir de evidencia academica real.
     *
     * Regla por curso:
     * - Con evaluacion publicada: 40% progreso de lecciones + 40% mejor nota + 20% finalizacion.
     * - Sin evaluacion publicada: 80% progreso de lecciones + 20% finalizacion.
     *
     * Si el mismo curso fue asignado mas de una vez, se conserva la mejor evidencia para
     * evitar que una reasignacion duplique o penalice artificialmente la skill.
     */
    public function perfilUsuario(int $idUsuario): array
    {
        $sql = "
            SELECT
                a.id_asignacion,
                a.estado AS asignacion_estado,
                cap.id_curso,
                cap.nombre AS capacitacion,
                c.titulo AS curso,
                s.id_skill,
                s.codigo,
                s.nombre,
                s.categoria,
                s.descripcion,
                s.icono,
                cs.peso,
                cs.nivel_objetivo,
                cs.origen,
                COALESCE(lp.total_lecciones, 0) AS total_lecciones,
                COALESCE(lp.completadas, 0) AS completadas,
                e.id_evaluacion,
                e.nota_minima,
                COALESCE(ie.mejor_nota, 0) AS mejor_nota,
                COALESCE(ie.intentos, 0) AS intentos
            FROM learning_asignaciones a
            INNER JOIN learning_capacitaciones cap ON cap.id_capacitacion = a.id_capacitacion
            INNER JOIN learning_cursos c ON c.id_curso = cap.id_curso
            INNER JOIN learning_curso_skills cs ON cs.id_curso = c.id_curso
            INNER JOIN learning_skills s ON s.id_skill = cs.id_skill
            LEFT JOIN (
                SELECT
                    ax.id_asignacion,
                    COUNT(DISTINCT l.id_leccion) AS total_lecciones,
                    COUNT(DISTINCT CASE WHEN pl.estado = 'completada' THEN l.id_leccion END) AS completadas
                FROM learning_asignaciones ax
                INNER JOIN learning_capacitaciones capx ON capx.id_capacitacion = ax.id_capacitacion
                INNER JOIN learning_curso_lecciones l ON l.id_curso = capx.id_curso AND l.obligatoria = 1
                LEFT JOIN learning_progreso_lecciones pl
                       ON pl.id_asignacion = ax.id_asignacion
                      AND pl.id_leccion = l.id_leccion
                GROUP BY ax.id_asignacion
            ) lp ON lp.id_asignacion = a.id_asignacion
            LEFT JOIN learning_evaluaciones e
                   ON e.id_curso = c.id_curso
                  AND e.estado = 'publicada'
            LEFT JOIN (
                SELECT
                    id_asignacion,
                    id_evaluacion,
                    MAX(porcentaje) AS mejor_nota,
                    COUNT(*) AS intentos
                FROM learning_intentos
                GROUP BY id_asignacion, id_evaluacion
            ) ie ON ie.id_asignacion = a.id_asignacion
                AND ie.id_evaluacion = e.id_evaluacion
            WHERE a.id_usuario = :usuario
              AND s.estado = 1
            ORDER BY s.categoria, s.nombre, c.titulo, a.id_asignacion
        ";

        try {
            $stmt = $this->conexion->prepare($sql);
            $stmt->execute([':usuario' => $idUsuario]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Permite que el resto de Learning Lab siga funcionando si aun no se instalo V3.2.5.1.
            if (in_array((string)$e->getCode(), ['42S02', '42S22'], true)) {
                return $this->perfilUsuarioVacio();
            }
            throw $e;
        }

        $skills = [];
        $cursosGlobales = [];

        foreach ($rows as $row) {
            $idSkill = (int)$row['id_skill'];
            $idCurso = (int)$row['id_curso'];
            $total = (int)$row['total_lecciones'];
            $completadas = (int)$row['completadas'];
            $finalizada = ((string)$row['asignacion_estado'] === 'completada');
            $progreso = $total > 0
                ? min(100, round(($completadas / $total) * 100, 2))
                : ($finalizada ? 100.0 : 0.0);

            $tieneEvaluacion = $row['id_evaluacion'] !== null;
            $mejorNota = $tieneEvaluacion ? min(100, max(0, (float)$row['mejor_nota'])) : null;

            if ($tieneEvaluacion) {
                $puntajeCurso = ($progreso * 0.40)
                    + (((float)$mejorNota) * 0.40)
                    + ($finalizada ? 20.0 : 0.0);
            } else {
                $puntajeCurso = ($progreso * 0.80)
                    + ($finalizada ? 20.0 : 0.0);
            }
            $puntajeCurso = round(min(100, max(0, $puntajeCurso)), 1);

            if (!isset($skills[$idSkill])) {
                $skills[$idSkill] = [
                    'id_skill' => $idSkill,
                    'codigo' => (string)$row['codigo'],
                    'nombre' => (string)$row['nombre'],
                    'categoria' => (string)$row['categoria'],
                    'descripcion' => (string)($row['descripcion'] ?? ''),
                    'icono' => (string)($row['icono'] ?: '🧩'),
                    'porcentaje' => 0.0,
                    'nivel' => 'pendiente',
                    'nivel_texto' => 'Pendiente',
                    'nivel_objetivo' => 'basico',
                    'nivel_objetivo_texto' => 'Básico',
                    'cursos_asociados' => 0,
                    'cursos_completados' => 0,
                    'cursos' => [],
                    '_cursos' => [],
                ];
            }

            $evidencia = [
                'id_curso' => $idCurso,
                'curso' => (string)$row['curso'],
                'capacitacion' => (string)$row['capacitacion'],
                'peso' => round((float)$row['peso'], 2),
                'nivel_objetivo' => (string)$row['nivel_objetivo'],
                'progreso' => $progreso,
                'tiene_evaluacion' => $tieneEvaluacion,
                'mejor_nota' => $mejorNota,
                'intentos' => (int)$row['intentos'],
                'completada' => $finalizada,
                'puntaje_curso' => $puntajeCurso,
                'origen_skill' => (string)($row['origen'] ?? 'manual'),
            ];

            // Si el curso fue asignado mas de una vez, conserva la evidencia con mejor puntaje.
            if (!isset($skills[$idSkill]['_cursos'][$idCurso])
                || $puntajeCurso > (float)$skills[$idSkill]['_cursos'][$idCurso]['puntaje_curso']) {
                $skills[$idSkill]['_cursos'][$idCurso] = $evidencia;
            }

            $actualObjetivo = $skills[$idSkill]['nivel_objetivo'];
            if ($this->valorNivelObjetivo((string)$row['nivel_objetivo']) > $this->valorNivelObjetivo($actualObjetivo)) {
                $skills[$idSkill]['nivel_objetivo'] = (string)$row['nivel_objetivo'];
            }
            $cursosGlobales[$idCurso] = true;
        }

        foreach ($skills as &$skill) {
            $sumaPonderada = 0.0;
            $sumaPesos = 0.0;
            $completados = 0;
            $cursos = array_values($skill['_cursos']);

            foreach ($cursos as $evidencia) {
                $peso = max(0.01, (float)$evidencia['peso']);
                $sumaPonderada += ((float)$evidencia['puntaje_curso']) * $peso;
                $sumaPesos += $peso;
                if (!empty($evidencia['completada'])) {
                    $completados++;
                }
            }

            $porcentaje = $sumaPesos > 0 ? round($sumaPonderada / $sumaPesos, 1) : 0.0;
            [$nivel, $nivelTexto] = $this->nivelDesdePorcentaje($porcentaje);
            $skill['porcentaje'] = $porcentaje;
            $skill['nivel'] = $nivel;
            $skill['nivel_texto'] = $nivelTexto;
            $skill['nivel_objetivo_texto'] = $this->textoNivelObjetivo((string)$skill['nivel_objetivo']);
            $skill['cursos_asociados'] = count($cursos);
            $skill['cursos_completados'] = $completados;
            usort($cursos, static function (array $a, array $b): int {
                $cmp = ((float)$b['puntaje_curso'] <=> (float)$a['puntaje_curso']);
                return $cmp !== 0 ? $cmp : strcmp((string)$a['curso'], (string)$b['curso']);
            });
            $skill['cursos'] = $cursos;
            unset($skill['_cursos']);
        }
        unset($skill);

        $skills = array_values($skills);
        usort($skills, static function (array $a, array $b): int {
            $cmp = ((float)$b['porcentaje'] <=> (float)$a['porcentaje']);
            return $cmp !== 0 ? $cmp : strcmp((string)$a['nombre'], (string)$b['nombre']);
        });

        $promedio = $skills
            ? round(array_sum(array_map(static fn(array $s): float => (float)$s['porcentaje'], $skills)) / count($skills), 1)
            : 0.0;
        $destacada = $skills[0] ?? null;

        return [
            'skills' => $skills,
            'resumen' => [
                'total_skills' => count($skills),
                'skills_en_desarrollo' => count(array_filter($skills, static fn(array $s): bool => (float)$s['porcentaje'] > 0)),
                'promedio' => $promedio,
                'cursos_con_skills' => count($cursosGlobales),
                'destacada_nombre' => $destacada['nombre'] ?? '',
                'destacada_porcentaje' => $destacada['porcentaje'] ?? 0,
            ],
        ];
    }

    private function perfilUsuarioVacio(): array
    {
        return [
            'skills' => [],
            'resumen' => [
                'total_skills' => 0,
                'skills_en_desarrollo' => 0,
                'promedio' => 0,
                'cursos_con_skills' => 0,
                'destacada_nombre' => '',
                'destacada_porcentaje' => 0,
            ],
        ];
    }

    private function nivelDesdePorcentaje(float $porcentaje): array
    {
        if ($porcentaje <= 0) return ['pendiente', 'Pendiente'];
        if ($porcentaje < 40) return ['desarrollo', 'En desarrollo'];
        if ($porcentaje < 60) return ['basico', 'Básico'];
        if ($porcentaje < 80) return ['intermedio', 'Intermedio'];
        return ['avanzado', 'Avanzado'];
    }

    private function valorNivelObjetivo(string $nivel): int
    {
        return ['basico' => 1, 'intermedio' => 2, 'avanzado' => 3][$nivel] ?? 1;
    }

    private function textoNivelObjetivo(string $nivel): string
    {
        return ['basico' => 'Básico', 'intermedio' => 'Intermedio', 'avanzado' => 'Avanzado'][$nivel] ?? 'Básico';
    }

    public function contextoCursoParaIA(int $idCurso): array
    {
        $cursoStmt = $this->conexion->prepare("SELECT * FROM learning_cursos WHERE id_curso=:id LIMIT 1");
        $cursoStmt->execute([':id'=>$idCurso]);
        $curso = $cursoStmt->fetch(PDO::FETCH_ASSOC);
        if (!$curso) {
            return ['curso'=>null,'total_lecciones'=>0,'lecciones_transcritas'=>0,'lecciones_sin_transcribir'=>0,'texto_analisis'=>''];
        }

        try {
            $stmt = $this->conexion->prepare("
                SELECT l.id_leccion,l.orden,l.id_video,
                       COALESCE(NULLIF(l.titulo_personalizado,''),v.titulo) AS titulo,
                       vt.id_transcripcion,vt.estado AS transcripcion_estado,vt.texto_completo
                FROM learning_curso_lecciones l
                INNER JOIN videos v ON v.id_video=l.id_video
                LEFT JOIN video_transcripciones vt ON vt.id_video=l.id_video
                WHERE l.id_curso=:curso
                ORDER BY l.orden,l.id_leccion
            ");
            $stmt->execute([':curso'=>$idCurso]);
            $lecciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new RuntimeException('El módulo de transcripciones todavía no está disponible. Importa primero la migración de transcripción V3.');
        }

        $bloques = [];
        $transcritas = 0;
        $presupuestoTotal = 48000;
        $usado = 0;

        foreach ($lecciones as $leccion) {
            if (($leccion['transcripcion_estado'] ?? '') !== 'completada') {
                continue;
            }
            $transcritas++;
            $texto = trim((string)($leccion['texto_completo'] ?? ''));

            if ($texto === '' && !empty($leccion['id_transcripcion'])) {
                $segStmt = $this->conexion->prepare("SELECT texto FROM video_transcripcion_segmentos WHERE id_transcripcion=:id ORDER BY orden,inicio_segundos");
                $segStmt->execute([':id'=>(int)$leccion['id_transcripcion']]);
                $texto = implode(' ', array_map(static fn($r) => trim((string)$r['texto']), $segStmt->fetchAll(PDO::FETCH_ASSOC)));
            }

            $texto = preg_replace('/\s+/u', ' ', $texto) ?? $texto;
            if ($texto === '') {
                continue;
            }

            $restante = $presupuestoTotal - $usado;
            if ($restante <= 1000) {
                break;
            }
            $maxLeccion = min(9000, $restante);
            $muestra = $this->muestraRepresentativa($texto, $maxLeccion);
            $bloque = "LECCIÓN " . (int)$leccion['orden'] . ': ' . (string)$leccion['titulo'] . "\n" . $muestra;
            $bloques[] = $bloque;
            $usado += strlen($bloque);
        }

        return [
            'curso'=>$curso,
            'total_lecciones'=>count($lecciones),
            'lecciones_transcritas'=>$transcritas,
            'lecciones_sin_transcribir'=>max(0, count($lecciones)-$transcritas),
            'texto_analisis'=>implode("\n\n---\n\n", $bloques),
        ];
    }

    private function muestraRepresentativa(string $texto, int $max): string
    {
        if (strlen($texto) <= $max) {
            return $texto;
        }

        $primero = (int)floor($max * 0.42);
        $medio = (int)floor($max * 0.20);
        $ultimo = $max - $primero - $medio;
        $len = strlen($texto);
        $inicioMedio = max(0, (int)floor(($len - $medio) / 2));

        return substr($texto, 0, $primero)
            . "\n[...fragmento intermedio...]\n"
            . substr($texto, $inicioMedio, $medio)
            . "\n[...fragmento final...]\n"
            . substr($texto, -$ultimo);
    }

    private function buscarIdPorNombreOCodigo(string $valor): int
    {
        $stmt = $this->conexion->prepare("SELECT id_skill FROM learning_skills WHERE LOWER(nombre)=LOWER(:valor) OR LOWER(codigo)=LOWER(:valor2) LIMIT 1");
        $stmt->execute([':valor'=>$valor, ':valor2'=>$this->generarCodigo($valor)]);
        return (int)($stmt->fetchColumn() ?: 0);
    }
}
