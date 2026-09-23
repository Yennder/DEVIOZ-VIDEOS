<?php
require_once __DIR__ . '/../models/Certificado.php';

class CertificadoController
{
    private Certificado $modelo;
    public function __construct(){ $this->modelo=new Certificado(); }
    public function emitirPorAsignacion($id){ return $this->modelo->emitirPorAsignacion((int)$id); }
    public function listarUsuario($idUsuario){ return $this->modelo->listarUsuario((int)$idUsuario); }
    public function listarAdmin($buscar='',$estado=''){ return $this->modelo->listarAdmin((string)$buscar,(string)$estado); }
    public function obtenerPorCodigo($codigo){ return $this->modelo->obtenerPorCodigo((string)$codigo); }
    public function cambiarEstado($id,$estado,$motivo=''){ return $this->modelo->cambiarEstado((int)$id,(string)$estado,(string)$motivo); }
}
