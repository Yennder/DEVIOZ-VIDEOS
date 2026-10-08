<?php
/**
 * HOTFIX V4.5.1.1 - Video promocional de la portada.
 * Solo se gestionan MP4 publicos. No modifica los videos protegidos del catalogo.
 */

if (!function_exists('deviozNombreVideoPortadaValido')) {
    function deviozNombreVideoPortadaValido($nombre): bool
    {
        return is_string($nombre)
            && preg_match('/\Aportada_[a-f0-9]{32}\.mp4\z/D', $nombre) === 1;
    }
}

if (!function_exists('deviozDirectorioVideosPortada')) {
    function deviozDirectorioVideosPortada(): string
    {
        return dirname(__DIR__) . '/assets/media/portadas/';
    }
}

if (!function_exists('deviozRutaVideoPortada')) {
    function deviozRutaVideoPortada($nombre): ?string
    {
        if (!deviozNombreVideoPortadaValido($nombre)) {
            return null;
        }
        return deviozDirectorioVideosPortada() . $nombre;
    }
}

if (!function_exists('deviozFuenteVideoPortada')) {
    /**
     * Devuelve una URL relativa si existe un MP4 valido, o null si no hay video.
     * El archivo legacy opcional permite conservar el soporte de V4.5.1.
     */
    function deviozFuenteVideoPortada(?string $configuracion, string $prefijoUrl = '../'): ?string
    {
        if ($configuracion === 'desactivado') {
            return null;
        }

        if (deviozNombreVideoPortadaValido($configuracion)) {
            $ruta = deviozRutaVideoPortada($configuracion);
            if ($ruta !== null && is_file($ruta)) {
                return $prefijoUrl . 'assets/media/portadas/' . rawurlencode($configuracion)
                    . '?v=' . (string)filemtime($ruta);
            }
            // Si hubo una carga anterior, no usar una URL remota ni un video distinto.
            return null;
        }

        if ($configuracion === null) {
            $legacy = dirname(__DIR__) . '/assets/media/portada-tech.mp4';
            if (is_file($legacy)) {
                return $prefijoUrl . 'assets/media/portada-tech.mp4?v=' . (string)filemtime($legacy);
            }
        }

        return null;
    }
}

if (!function_exists('deviozSubirVideoPortada')) {
    /** @throws RuntimeException cuando el archivo no es apto para la portada. */
    function deviozSubirVideoPortada($archivo): string
    {
        $limiteBytes = 100 * 1024 * 1024; // Hasta 100 MB. Sugerido: menos de 20 MB.

        if (!is_array($archivo)
            || !isset($archivo['error'], $archivo['name'], $archivo['tmp_name'], $archivo['size'])
            || !is_int($archivo['error'])
            || !is_string($archivo['name'])
            || !is_string($archivo['tmp_name'])
            || !is_int($archivo['size'])) {
            throw new RuntimeException('Selecciona un archivo de video MP4 valido.');
        }

        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            $errores = [
                UPLOAD_ERR_INI_SIZE => 'El video supera el limite de upload_max_filesize en PHP. Revisa php.ini de XAMPP.',
                UPLOAD_ERR_FORM_SIZE => 'El video supera el limite permitido por el formulario.',
                UPLOAD_ERR_PARTIAL => 'El archivo solo se subio parcialmente. Intentalo nuevamente.',
                UPLOAD_ERR_NO_FILE => 'Debes seleccionar un video MP4.',
            ];
            throw new RuntimeException($errores[$archivo['error']] ?? 'No se pudo recibir el archivo de video.');
        }

        if ($archivo['size'] <= 0 || $archivo['size'] > $limiteBytes) {
            throw new RuntimeException('El video debe pesar entre 1 byte y 100 MB. Usa un clip corto y optimizado.');
        }

        if (strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION)) !== 'mp4') {
            throw new RuntimeException('Solo se permite el formato MP4 (recomendado: H.264).');
        }

        if (!is_uploaded_file($archivo['tmp_name'])) {
            throw new RuntimeException('La carga del video no es valida. Vuelve a seleccionarlo.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($archivo['tmp_name']);
        if (!in_array($mime, ['video/mp4', 'application/mp4', 'application/octet-stream'], true)) {
            throw new RuntimeException('El archivo no tiene un tipo de contenido MP4 valido.');
        }

        $handle = @fopen($archivo['tmp_name'], 'rb');
        if ($handle === false) {
            throw new RuntimeException('No se pudo verificar el contenido del MP4.');
        }
        $cabecera = fread($handle, 32);
        fclose($handle);
        if (!is_string($cabecera) || strlen($cabecera) < 12 || substr($cabecera, 4, 4) !== 'ftyp') {
            throw new RuntimeException('El archivo seleccionado no parece ser un contenedor MP4 valido.');
        }

        $directorio = deviozDirectorioVideosPortada();
        if (!is_dir($directorio) && !@mkdir($directorio, 0755, true) && !is_dir($directorio)) {
            throw new RuntimeException('No se pudo crear la carpeta de videos de portada.');
        }
        if (!is_writable($directorio)) {
            throw new RuntimeException('La carpeta assets/media/portadas no permite escritura.');
        }

        try {
            $nombre = 'portada_' . bin2hex(random_bytes(16)) . '.mp4';
        } catch (Throwable $e) {
            throw new RuntimeException('No se pudo generar un nombre seguro para el video.', 0, $e);
        }
        if (!move_uploaded_file($archivo['tmp_name'], $directorio . $nombre)) {
            throw new RuntimeException('No se pudo guardar el MP4. Revisa los permisos de la carpeta.');
        }

        return $nombre;
    }
}

if (!function_exists('deviozEliminarVideoPortada')) {
    function deviozEliminarVideoPortada($nombre): void
    {
        $ruta = deviozRutaVideoPortada($nombre);
        if ($ruta !== null && is_file($ruta)) {
            @unlink($ruta);
        }
    }
}
