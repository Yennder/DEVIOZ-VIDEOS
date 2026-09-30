<?php
require_once __DIR__ . '/../services/EvaluacionIAService.php';

class EvaluacionIAController
{
    private EvaluacionIAService $service;
    public function __construct(){ $this->service = new EvaluacionIAService(); }
    public function panel($curso){ return $this->service->panel((int)$curso); }
    public function generar($curso,$datos,$usuario){ return $this->service->generar((int)$curso,is_array($datos)?$datos:[],(int)$usuario); }
    public function actualizarPregunta($curso,$pregunta,$datos){ return $this->service->actualizarPregunta((int)$curso,(int)$pregunta,is_array($datos)?$datos:[]); }
    public function eliminarPregunta($curso,$pregunta){ return $this->service->eliminarPregunta((int)$curso,(int)$pregunta); }
    public function descartar($curso,$borrador){ return $this->service->descartar((int)$curso,(int)$borrador); }
    public function aprobar($curso,$borrador,$usuario){ return $this->service->aprobar((int)$curso,(int)$borrador,(int)$usuario); }
}
