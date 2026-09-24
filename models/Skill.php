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
