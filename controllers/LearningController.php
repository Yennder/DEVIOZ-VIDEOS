<?php
require_once __DIR__ . '/../models/Learning.php';

class LearningController
{
    private Learning $modelo;
    public function __construct(){ $this->modelo = new Learning(); }
    public function listarCursosAdmin($b='',$e=''){ return $this->modelo->listarCursosAdmin($b,$e); }
    public function listarCursosPublicados(){ return $this->modelo->listarCursosPublicados(); }
    public function buscarCurso($id){ return $this->modelo->buscarCurso((int)$id); }
    public function guardarCurso($d,$u){ return $this->modelo->guardarCurso($d,(int)$u); }
    public function eliminarCurso($id){ return $this->modelo->eliminarCurso((int)$id); }
    public function videosDisponibles($b=''){ return $this->modelo->videosDisponibles($b); }
    public function leccionesCurso($id){ return $this->modelo->leccionesCurso((int)$id); }
    public function agregarLeccion($c,$v,$t,$d,$o,$ob=true){ return $this->modelo->agregarLeccion((int)$c,(int)$v,$t,$d,(int)$o,(bool)$ob); }
    public function actualizarLeccion($l,$c,$d){ return $this->modelo->actualizarLeccion((int)$l,(int)$c,$d); }
    public function eliminarLeccion($l,$c){ return $this->modelo->eliminarLeccion((int)$l,(int)$c); }
    public function usuariosAsignables(){ return $this->modelo->usuariosAsignables(); }
    public function crearCapacitacion($d,$u,$a){ return $this->modelo->crearCapacitacion($d,$u,(int)$a); }
    public function actualizarCapacitacion($id,$d){ return $this->modelo->actualizarCapacitacion((int)$id,$d); }
    public function listarCapacitacionesAdmin(){ return $this->modelo->listarCapacitacionesAdmin(); }
    public function buscarCapacitacion($id){ return $this->modelo->buscarCapacitacion((int)$id); }
    public function asignacionesCapacitacion($id){ return $this->modelo->asignacionesCapacitacion((int)$id); }
    public function asignarUsuarios($id,$u){ return $this->modelo->asignarUsuarios((int)$id,$u); }
    public function quitarAsignacion($id,$cap){ return $this->modelo->quitarAsignacion((int)$id,(int)$cap); }
    public function eliminarCapacitacion($id){ return $this->modelo->eliminarCapacitacion((int)$id); }
    public function misAsignaciones($u){ return $this->modelo->misAsignaciones((int)$u); }
    public function catalogoCursosParaUsuario($u){ return $this->modelo->catalogoCursosParaUsuario((int)$u); }
    public function detalleAsignacion($a,$u){ return $this->modelo->detalleAsignacion((int)$a,(int)$u); }
    public function leccionesAsignacion($a,$u){ return $this->modelo->leccionesAsignacion((int)$a,(int)$u); }
    public function marcarLeccionCompleta($a,$l,$u){ return $this->modelo->marcarLeccionCompleta((int)$a,(int)$l,(int)$u); }
    public function listarEvaluacionesAdmin(){ return $this->modelo->listarEvaluacionesAdmin(); }
    public function evaluacionCurso($c,$p=false){ return $this->modelo->evaluacionCurso((int)$c,(bool)$p); }
    public function guardarEvaluacion($c,$d){ return $this->modelo->guardarEvaluacion((int)$c,$d); }
    public function preguntasEvaluacion($e){ return $this->modelo->preguntasEvaluacion((int)$e); }
    public function agregarPregunta($e,$d){ return $this->modelo->agregarPregunta((int)$e,$d); }
    public function eliminarPregunta($p,$e){ return $this->modelo->eliminarPregunta((int)$p,(int)$e); }
    public function intentosAsignacion($a,$e){ return $this->modelo->intentosAsignacion((int)$a,(int)$e); }
    public function puedeRendirEvaluacion($a,$u){ return $this->modelo->puedeRendirEvaluacion((int)$a,(int)$u); }
    public function registrarIntento($a,$u,$r){ return $this->modelo->registrarIntento((int)$a,(int)$u,$r); }
    public function listarLogrosAdmin(){ return $this->modelo->listarLogrosAdmin(); }
    public function guardarLogro($d){ return $this->modelo->guardarLogro($d); }
    public function buscarLogro($id){ return $this->modelo->buscarLogro((int)$id); }
    public function logrosUsuario($u){ return $this->modelo->logrosUsuario((int)$u); }
    public function resumenUsuario($u){ return $this->modelo->resumenUsuario((int)$u); }
    public function statsAdmin(){ return $this->modelo->statsAdmin(); }
    public function seguimientoAdmin(){ return $this->modelo->seguimientoAdmin(); }
}
