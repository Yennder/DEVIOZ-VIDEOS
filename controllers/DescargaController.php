<?php

require_once __DIR__ . '/../models/DescargaAutorizada.php';
require_once __DIR__ . '/../models/Notificacion.php';

class DescargaController
{
    private DescargaAutorizada $modelo;
    private Notificacion $notificaciones;

    public function __construct()
    {
        $this->modelo = new DescargaAutorizada();
        $this->notificaciones = new Notificacion();
    }

    public function tablasDisponibles(): bool { return $this->modelo->tablasDisponibles(); }
    public function solicitudesDisponibles(): bool { return $this->modelo->solicitudesDisponibles(); }
    public function usuariosActivos(): array { return $this->modelo->usuariosActivos(); }
    public function videosPublicados(): array { return $this->modelo->videosPublicados(); }
    public function listar(string $buscar = '', string $estado = ''): array { return $this->modelo->listar($buscar, $estado); }
    public function historial(int $limite = 100): array { return $this->modelo->historial($limite); }
    public function crear(int $usuario, int $video, int $admin, int $horas, int $usos): string { return $this->modelo->crear($usuario, $video, $admin, $horas, $usos); }
    public function cambiarEstado(int $id, string $estado): bool { return $this->modelo->cambiarEstado($id, $estado); }
    public function validarYConsumir(string $codigo, int $usuario, int $video, string $ip, string $ua): array { return $this->modelo->validarYConsumir($codigo, $usuario, $video, $ip, $ua); }
    public function solicitudActualUsuario(int $usuario, int $video): ?array { return $this->modelo->solicitudActualUsuario($usuario, $video); }
    public function contarSolicitudesPendientes(): int { return $this->modelo->contarSolicitudesPendientes(); }
    public function listarSolicitudes(string $buscar = '', string $estado = ''): array { return $this->modelo->listarSolicitudes($buscar, $estado); }
    public function obtenerSolicitud(int $id): ?array { return $this->modelo->obtenerSolicitud($id); }
    public function listarSolicitudesUsuario(int $usuario, int $limite = 100): array { return $this->modelo->listarSolicitudesUsuario($usuario, $limite); }

    public function solicitar(int $usuario, int $video, string $motivo = ''): int
    {
        $id = $this->modelo->crearSolicitud($usuario, $video, $motivo);
        $solicitud = $this->modelo->obtenerSolicitud($id);
        if ($solicitud) {
            foreach ($this->modelo->administradoresActivos() as $admin) {
                $this->notificaciones->crear(
                    (int)$admin['id_usuario'],
                    'descarga_solicitud_nueva',
                    'Nueva solicitud de descarga',
                    $solicitud['usuario'] . ' solicito descargar "' . $solicitud['video'] . '".',
                    '/DEVIOZ-VIDEOS/admin/descargas/solicitud.php?id=' . $id,
                    '🔐',
                    'descarga_solicitud:' . $id
                );
            }
        }
        return $id;
    }

    public function aprobarSolicitud(int $id, int $admin, int $horas = 24, int $usos = 1): array
    {
        $resultado = $this->modelo->aprobarSolicitud($id, $admin, $horas, $usos);
        $this->notificaciones->crear(
            (int)$resultado['id_usuario'],
            'descarga_aprobada',
            'Descarga aprobada',
            'Tu solicitud fue aprobada. Codigo: ' . $resultado['codigo'] . '. Valido hasta ' . date('d/m/Y H:i', strtotime($resultado['expira_en'])) . '.',
            '/DEVIOZ-VIDEOS/public/solicitud_descarga.php?id=' . $id,
            '✅',
            'descarga_aprobada:' . $id . ':' . $resultado['id_codigo']
        );
        return $resultado;
    }

    public function rechazarSolicitud(int $id, int $admin, string $motivo = ''): array
    {
        $resultado = $this->modelo->rechazarSolicitud($id, $admin, $motivo);
        $this->notificaciones->crear(
            (int)$resultado['id_usuario'],
            'descarga_rechazada',
            'Solicitud de descarga rechazada',
            $motivo !== '' ? ('Motivo: ' . $motivo) : 'El administrador no autorizo la descarga solicitada.',
            '/DEVIOZ-VIDEOS/public/solicitud_descarga.php?id=' . $id,
            '🚫',
            'descarga_rechazada:' . $id
        );
        return $resultado;
    }

}
