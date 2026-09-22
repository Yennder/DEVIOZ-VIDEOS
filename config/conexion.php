<?php

class Conexion
{
    private string $host;
    private string $dbname;
    private string $usuario;
    private string $password;

    public function __construct()
    {
        $this->host = getenv('DEVIOZ_DB_HOST') ?: 'localhost';
        $this->dbname = getenv('DEVIOZ_DB_NAME') ?: 'devioz_videos';
        $this->usuario = getenv('DEVIOZ_DB_USER') ?: 'root';
        $this->password = getenv('DEVIOZ_DB_PASSWORD') ?: '';
    }

    public function conectar(): PDO
    {
        try {
            return new PDO(
                'mysql:host=' . $this->host . ';dbname=' . $this->dbname . ';charset=utf8mb4',
                $this->usuario,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        } catch (PDOException $e) {
            error_log('DEVIOZ VIDEOS - Error de base de datos: ' . $e->getMessage());
            throw new RuntimeException(
                'No se pudo conectar con la base de datos. Revisa la configuración de MySQL/MariaDB.'
            );
        }
    }
}
