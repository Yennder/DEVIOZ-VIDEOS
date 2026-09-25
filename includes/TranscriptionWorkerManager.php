<?php

class TranscriptionWorkerManager
{
    private string $projectRoot;
    private string $workerDir;
    private string $runtimeDir;
    private string $statusFile;
    private string $logFile;
    private string $errorLogFile;
    private string $windowsLauncher;

    public function __construct(?string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot ?: dirname(__DIR__);
        $this->workerDir = $this->projectRoot . DIRECTORY_SEPARATOR . 'python' . DIRECTORY_SEPARATOR . 'transcripcion';
        $this->runtimeDir = $this->workerDir . DIRECTORY_SEPARATOR . 'runtime';
        $this->statusFile = $this->runtimeDir . DIRECTORY_SEPARATOR . 'worker_status.json';
        $this->logFile = $this->runtimeDir . DIRECTORY_SEPARATOR . 'worker.log';
        $this->errorLogFile = $this->runtimeDir . DIRECTORY_SEPARATOR . 'worker_error.log';
        $this->windowsLauncher = $this->workerDir . DIRECTORY_SEPARATOR . 'start_worker_hidden.vbs';
    }

    public function status(): array
    {
        $raw = $this->readStatusFile();
        $heartbeatTs = isset($raw['heartbeat_ts']) ? (int)$raw['heartbeat_ts'] : 0;
        $age = $heartbeatTs > 0 ? max(0, time() - $heartbeatTs) : null;
        $pid = isset($raw['pid']) ? (int)$raw['pid'] : 0;
        $heartbeatFresh = $age !== null && $age <= 15;
        $processAlive = $pid > 0 ? $this->processAlive($pid) : false;
        $active = $heartbeatFresh && ($processAlive || !$this->canExec());

        if (!$raw) {
            $state = 'detenido';
        } elseif ($active) {
            $state = (($raw['state'] ?? '') === 'processing') ? 'procesando' : 'activo';
        } elseif (($raw['state'] ?? '') === 'stopped') {
            $state = 'detenido';
        } else {
            $state = 'interrumpido';
        }

        return [
            'active' => $active,
            'state' => $state,
            'pid' => $pid,
            'heartbeat_age' => $age,
            'heartbeat_at' => $raw['heartbeat_at'] ?? null,
            'started_at' => $raw['started_at'] ?? null,
            'message' => (string)($raw['message'] ?? ''),
            'current_job' => isset($raw['current_job']) ? (int)$raw['current_job'] : null,
            'current_video' => isset($raw['current_video']) ? (int)$raw['current_video'] : null,
            'current_title' => (string)($raw['current_title'] ?? ''),
            'model' => (string)($raw['model'] ?? ''),
            'device' => (string)($raw['device'] ?? ''),
            'compute_type' => (string)($raw['compute_type'] ?? ''),
            'platform' => PHP_OS_FAMILY,
            'python_path' => $this->pythonPath(),
            'python_ready' => is_file($this->pythonPath()),
            'exec_available' => $this->canExec(),
            'log_file' => $this->logFile,
        ];
    }

    public function start(): array
    {
        $current = $this->status();
        if ($current['active']) {
            return ['ok' => true, 'message' => 'El worker ya esta activo.', 'status' => $current];
        }

        if (!$this->canExec()) {
            throw new RuntimeException('PHP no permite ejecutar procesos locales. Usa INICIAR_WORKER.bat o habilita exec().');
        }

        $python = $this->pythonPath();
        $worker = $this->workerDir . DIRECTORY_SEPARATOR . 'worker.py';
        if (!is_file($python)) {
            throw new RuntimeException('No se encontro el entorno Python local. Ejecuta INSTALAR_TRANSCRIPCION.bat.');
        }
        if (!is_file($worker)) {
            throw new RuntimeException('No se encontro python/transcripcion/worker.py.');
        }

        // Si existe un PID viejo con heartbeat vencido, no levantamos una segunda instancia.
        // Primero cerramos ese proceso residual y luego arrancamos limpio.
        $stalePid = (int)($current['pid'] ?? 0);
        if ($stalePid > 0 && $this->processAlive($stalePid)) {
            $this->terminateProcess($stalePid);
        }

        $this->ensureRuntimeDir();
        @file_put_contents($this->logFile, '', LOCK_EX);
        @file_put_contents($this->errorLogFile, '', LOCK_EX);

        if (PHP_OS_FAMILY === 'Windows') {
            if (!is_file($this->windowsLauncher)) {
                throw new RuntimeException('No se encontro python/transcripcion/start_worker_hidden.vbs.');
            }

            // WScript lanza el proceso con waitOnReturn=False. De esta manera Apache no
            // queda esperando al worker y la interfaz vuelve inmediatamente al navegador.
            $command = 'wscript.exe //B //Nologo '
                . escapeshellarg($this->windowsLauncher) . ' '
                . escapeshellarg($python) . ' '
                . escapeshellarg($worker) . ' '
                . escapeshellarg($this->workerDir) . ' '
                . escapeshellarg($this->logFile) . ' '
                . escapeshellarg($this->errorLogFile);
            $output = [];
            $code = 0;
            exec($command, $output, $code);
            if ($code !== 0) {
                throw new RuntimeException('Windows no pudo iniciar el worker en segundo plano. Puedes usar INICIAR_WORKER.bat como respaldo.');
            }
        } else {
            $command = 'cd ' . escapeshellarg($this->workerDir)
                . ' && nohup ' . escapeshellarg($python)
                . ' ' . escapeshellarg($worker)
                . ' --daemon --device cpu --compute-type int8'
                . ' >> ' . escapeshellarg($this->logFile) . ' 2>&1 < /dev/null &';
            exec($command);
        }

        for ($i = 0; $i < 20; $i++) {
            usleep(250000);
            $status = $this->status();
            if ($status['active']) {
                return ['ok' => true, 'message' => 'Worker iniciado correctamente.', 'status' => $status];
            }
        }

        throw new RuntimeException('Se intento iniciar el worker, pero no se recibio heartbeat. Abre Diagnostico para revisar el entorno.');
    }

    public function stop(): array
    {
        $status = $this->status();
        $pid = (int)($status['pid'] ?? 0);
        if ($pid <= 0) {
            $this->writeStoppedStatus('Worker detenido.');
            return ['ok' => true, 'message' => 'El worker ya estaba detenido.', 'status' => $this->status()];
        }
        if (!$this->canExec()) {
            throw new RuntimeException('PHP no puede detener procesos porque exec() esta deshabilitado.');
        }

        // Tambien elimina procesos con heartbeat vencido: pueden seguir vivos aunque
        // el panel ya los considere interrumpidos.
        if ($this->processAlive($pid)) {
            $this->terminateProcess($pid);
        }

        $this->writeStoppedStatus('Worker detenido desde el panel administrativo.');
        return ['ok' => true, 'message' => 'Worker detenido.', 'status' => $this->status()];
    }

    public function restart(): array
    {
        $this->stop();
        usleep(500000);
        return $this->start();
    }

    public function diagnostics(): array
    {
        $python = $this->pythonPath();
        $script = $this->workerDir . DIRECTORY_SEPARATOR . 'diagnostico.py';
        $result = [
            'worker' => $this->status(),
            'python_ready' => is_file($python),
            'diagnostic_ok' => false,
            'details' => [],
            'error' => null,
        ];

        if (!is_file($python)) {
            $result['error'] = 'No existe el entorno virtual de Python.';
            return $result;
        }
        if (!is_file($script)) {
            $result['error'] = 'No existe diagnostico.py.';
            return $result;
        }
        if (!$this->canExec()) {
            $result['error'] = 'PHP tiene exec() deshabilitado.';
            return $result;
        }

        $output = [];
        $code = 0;
        exec(escapeshellarg($python) . ' ' . escapeshellarg($script) . ' --json 2>&1', $output, $code);
        $json = trim(implode("\n", $output));
        $decoded = json_decode($json, true);
        if (is_array($decoded)) {
            $result['details'] = $decoded;
            $result['diagnostic_ok'] = $code === 0;
        } else {
            $result['error'] = $json !== '' ? $json : 'No se obtuvo respuesta del diagnostico.';
        }
        return $result;
    }

    public function tailLog(int $lines = 30): string
    {
        $all = [];
        foreach ([$this->logFile, $this->errorLogFile] as $file) {
            if (!is_file($file)) {
                continue;
            }
            $contents = @file($file, FILE_IGNORE_NEW_LINES);
            if (is_array($contents)) {
                $all = array_merge($all, $contents);
            }
        }
        if (!$all) {
            return '';
        }
        return implode("\n", array_slice($all, -max(1, min(100, $lines))));
    }

    private function pythonPath(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return $this->workerDir . DIRECTORY_SEPARATOR . '.venv' . DIRECTORY_SEPARATOR . 'Scripts' . DIRECTORY_SEPARATOR . 'python.exe';
        }
        return $this->workerDir . DIRECTORY_SEPARATOR . '.venv' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'python';
    }

    private function readStatusFile(): array
    {
        if (!is_file($this->statusFile)) {
            return [];
        }
        $raw = @file_get_contents($this->statusFile);
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function processAlive(int $pid): bool
    {
        if ($pid <= 0 || !$this->canExec()) {
            return false;
        }
        if (PHP_OS_FAMILY === 'Windows') {
            $output = [];
            $code = 0;
            exec('tasklist /FI "PID eq ' . $pid . '" /NH', $output, $code);
            if ($code !== 0) {
                return false;
            }
            $text = implode("\n", $output);
            return str_contains($text, (string)$pid) && !str_contains(strtolower($text), 'no tasks');
        }
        $output = [];
        $code = 0;
        exec('kill -0 ' . $pid . ' 2>/dev/null', $output, $code);
        return $code === 0;
    }


    private function terminateProcess(int $pid): void
    {
        if ($pid <= 0 || !$this->canExec()) {
            return;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $output = [];
            $code = 0;
            exec('taskkill /PID ' . $pid . ' /T /F', $output, $code);
        } else {
            $output = [];
            $code = 0;
            exec('kill ' . $pid . ' 2>/dev/null', $output, $code);
        }

        // Espera breve para no reencolar/iniciar mientras el proceso anterior aun se esta cerrando.
        for ($i = 0; $i < 20; $i++) {
            if (!$this->processAlive($pid)) {
                return;
            }
            usleep(100000);
        }

        if (PHP_OS_FAMILY !== 'Windows' && $this->processAlive($pid)) {
            exec('kill -9 ' . $pid . ' 2>/dev/null');
        }
    }

    private function writeStoppedStatus(string $message): void
    {
        $this->ensureRuntimeDir();
        $payload = [
            'state' => 'stopped',
            'pid' => null,
            'heartbeat_ts' => time(),
            'heartbeat_at' => date('c'),
            'message' => $message,
        ];
        @file_put_contents($this->statusFile, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    private function ensureRuntimeDir(): void
    {
        if (!is_dir($this->runtimeDir)) {
            @mkdir($this->runtimeDir, 0775, true);
        }
    }

    private function canExec(): bool
    {
        if (!function_exists('exec')) {
            return false;
        }
        $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
        return !in_array('exec', $disabled, true);
    }

}
