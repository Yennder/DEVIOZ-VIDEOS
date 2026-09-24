<?php
require_once __DIR__ . '/../models/Skill.php';
require_once __DIR__ . '/../services/SkillAIAnalyzer.php';

class SkillController
{
    private Skill $modelo;

    public function __construct()
    {
        $this->modelo = new Skill();
    }

    public function listar($buscar='',$categoria='',$estado=''){ return $this->modelo->listar((string)$buscar,(string)$categoria,(string)$estado); }
    public function categorias(){ return $this->modelo->categorias(); }
    public function buscar($id){ return $this->modelo->buscar((int)$id); }
    public function guardar($datos){ return $this->modelo->guardar($datos); }
    public function eliminar($id){ return $this->modelo->eliminar((int)$id); }
    public function skillsCurso($idCurso){ return $this->modelo->skillsCurso((int)$idCurso); }
    public function guardarSkillsCurso($idCurso,$ids,$pesos,$niveles){ return $this->modelo->guardarSkillsCurso((int)$idCurso,(array)$ids,(array)$pesos,(array)$niveles); }
    public function contextoCursoIA($idCurso){ return $this->modelo->contextoCursoParaIA((int)$idCurso); }

    public function analizarCursoIA($idCurso): array
    {
        $analizador = new SkillAIAnalyzer($this->modelo);
        return $analizador->analizarCurso((int)$idCurso);
    }

    public function guardarSugerenciasIA($idCurso, array $sugerencias, string $provider, string $model): void
    {
        $this->modelo->guardarSkillsCursoIA((int)$idCurso, $sugerencias, $provider, $model);
    }
}
