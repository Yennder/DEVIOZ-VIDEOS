<?php

class KnowledgeIndexManager
{
    private string $projectRoot;
    private string $workerDir;
    private string $runtimeDir;
    private string $statusFile;
    private string $logFile;
    private string $errorLogFile;
    private string $windowsLauncher;

    public function __construct()
    {
        $this->projectRoot = dirname(__DIR__);
        $this->workerDir = $this->projectRoot . DIRECTORY_SEPARATOR . 'python' . DIRECTORY_SEPARATOR . 'conocimiento';
        $this->runtimeDir = $this->workerDir . DIRECTORY_SEPARATOR . 'runtime';
        $this->statusFile = $this->runtimeDir . DIRECTORY_SEPARATOR . 'index_status.json';
        $this->logFile = $this->runtimeDir . DIRECTORY_SEPARATOR . 'indexer.log';
        $this->errorLogFile = $this->runtimeDir . DIRECTORY_SEPARATOR . 'indexer-error.log';
        $this->windowsLauncher = $this->workerDir . DIRECTORY_SEPARATOR . 'start_indexer_hidden.vbs';
    }

    public function status(): array
    {
        $raw = $this->readStatusFile();
        $pid = (int)($raw['pid'] ?? 0);
        $heartbeat = (int)($raw['heartbeat_ts'] ?? 0);
        $state = (string)($raw['state'] ?? 'stopped');
        $fresh = $heartbeat > 0 && (time() - $heartbeat) <= 25;
        $alive = $pid > 0 ? $this->processAlive($pid) : false;
        $active = in_array($state, ['loading', 'indexing'], true) && $fresh && $alive;

        if (!$active && in_array($state, ['loading', 'indexing'], true) && !$fresh) {
            $state = 'interrupted';
        }

        return [
            'active' => $active,
            'state' => $state,
            'pid' => $pid ?: null,
            'heartbeat_at' => (string)($raw['heartbeat_at'] ?? ''),
            'message' => (string)($raw['message'] ?? ''),
            'current_video' => isset($raw['current_video']) ? (int)$raw['current_video'] : null,
            'current_title' => (string)($raw['current_title'] ?? ''),
            'processed' => (int)($raw['processed'] ?? 0),
            'total' => (int)($raw['total'] ?? 0),
            'chunks' => (int)($raw['chunks'] ?? 0),
            'model' => (string)($raw['model'] ?? $this->modelName()),
            'python_path' => $this->pythonPath(),
            'python_ready' => is_file($this->pythonPath()),
            'exec_available' => $this->canExec(),
        ];
    }

    public function start(bool $rebuild = false): array
    {
        $current = $this->status();
        if ($current['active']) {
            return ['ok' => true, 'message' => 'El indexador ya esta activo.', 'status' => $current];
        }

        if (!$this->canExec()) {
            throw new RuntimeException('PHP no permite ejecutar procesos locales. Usa INICIAR_INDEXADOR.bat como respaldo o habilita exec().');
        }

        $python = $this->pythonPath();
        $script = $this->workerDir . DIRECTORY_SEPARATOR . 'indexar.py';
        if (!is_file($python)) {
            throw new RuntimeException('No se encontro el entorno local de RAG. Ejecuta python/conocimiento/INSTALAR_RAG_LOCAL.bat.');
        }
        if (!is_file($script)) {
            throw new RuntimeException('No se encontro python/conocimiento/indexar.py.');
        }

        $stalePid = (int)($current['pid'] ?? 0);
        if ($stalePid > 0 && $this->processAlive($stalePid)) {
            $this->terminateProcess($stalePid);
        }

        $this->ensureRuntimeDir();
        @file_put_contents($this->logFile, '', LOCK_EX);
        @file_put_contents($this->errorLogFile, '', LOCK_EX);

        $args = $rebuild ? '--rebuild' : '--pending';

        if (PHP_OS_FAMILY === 'Windows') {
            if (!is_file($this->windowsLauncher)) {
                throw new RuntimeException('No se encontro start_indexer_hidden.vbs.');
            }
            $command = 'wscript.exe //B //Nologo '
                . escapeshellarg($this->windowsLauncher) . ' '
                . escapeshellarg($python) . ' '
                . escapeshellarg($script) . ' '
                . escapeshellarg($this->workerDir) . ' '
                . escapeshellarg($this->logFile) . ' '
                . escapeshellarg($this->errorLogFile) . ' '
                . escapeshellarg($args);
            $output = [];
            $code = 0;
            exec($command, $output, $code);
            if ($code !== 0) {
                throw new RuntimeException('Windows no pudo iniciar el indexador en segundo plano.');
            }
        } else {
            $command = 'cd ' . escapeshellarg($this->workerDir)
                . ' && nohup ' . escapeshellarg($python)
                . ' ' . escapeshellarg($script) . ' ' . $args
                . ' >> ' . escapeshellarg($this->logFile) . ' 2>> ' . escapeshellarg($this->errorLogFile)
                . ' < /dev/null &';
            exec($command);
        }

        for ($i = 0; $i < 24; $i++) {
            usleep(250000);
            $status = $this->status();
            if ($status['active'] || in_array($status['state'], ['completed', 'idle'], true)) {
                return ['ok' => true, 'message' => $rebuild ? 'Reconstruccion iniciada.' : 'Indexacion iniciada.', 'status' => $status];
            }
        }

        throw new RuntimeException('Se intento iniciar el indexador, pero no se recibio heartbeat. Abre Diagnostico RAG para revisar el entorno.');
    }

    public function stop(): array
    {
        $status = $this->status();
        $pid = (int)($status['pid'] ?? 0);
        if ($pid > 0 && $this->canExec() && $this->processAlive($pid)) {
            $this->terminateProcess($pid);
        }
        $this->writeStoppedStatus('Indexador detenido desde el panel administrativo.');
        return ['ok' => true, 'message' => 'Indexador detenido.', 'status' => $this->status()];
    }

    public function diagnostics(): array
    {
        $python = $this->pythonPath();
        $script = $this->workerDir . DIRECTORY_SEPARATOR . 'diagnostico.py';
        $result = [
            'status' => $this->status(),
            'python_ready' => is_file($python),
            'diagnostic_ok' => false,
            'details' => [],
            'error' => null,
        ];

        if (!is_file($python)) {
            $result['error'] = 'No existe el entorno virtual de RAG.';
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

    public function search(string $query, int $topK = 6, string $scope = 'global', int $scopeId = 0): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }
        if (mb_strlen($query) > 500) {
            throw new RuntimeException('La consulta es demasiado larga.');
        }
        if (!$this->canExec()) {
            throw new RuntimeException('PHP no permite ejecutar la busqueda semantica local.');
        }

        $python = $this->pythonPath();
        $script = $this->workerDir . DIRECTORY_SEPARATOR . 'buscar.py';
        if (!is_file($python)) {
            throw new RuntimeException('Primero instala el entorno RAG local.');
        }
        if (!is_file($script)) {
            throw new RuntimeException('No se encontro buscar.py.');
        }

        $this->ensureRuntimeDir();
        $queryFile = $this->runtimeDir . DIRECTORY_SEPARATOR . 'query-' . bin2hex(random_bytes(8)) . '.txt';
        file_put_contents($queryFile, $query, LOCK_EX);

        try {
            $command = escapeshellarg($python)
                . ' ' . escapeshellarg($script)
                . ' --query-file ' . escapeshellarg($queryFile)
                . ' --top-k ' . max(1, min(20, $topK))
                . ' --scope ' . escapeshellarg(in_array($scope, ['global','video','curso'], true) ? $scope : 'global')
                . ' --scope-id ' . max(0, $scopeId)
                . ' --json';
            $output = [];
            $code = 0;
            exec($command . ' 2>&1', $output, $code);
            $raw = trim(implode("\n", $output));
            $decoded = json_decode($raw, true);
            if ($code !== 0 || !is_array($decoded)) {
                throw new RuntimeException($raw !== '' ? $raw : 'No se pudo ejecutar la busqueda semantica.');
            }
            if (empty($decoded['ok'])) {
                throw new RuntimeException((string)($decoded['error'] ?? 'Busqueda semantica no disponible.'));
            }
            return is_array($decoded['results'] ?? null) ? $decoded['results'] : [];
        } finally {
            @unlink($queryFile);
        }
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

    public function modelName(): string
    {
        return getenv('DEVIOZ_EMBEDDING_MODEL') ?: 'sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2';
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
            $text = strtolower(implode("\n", $output));
            return str_contains($text, (string)$pid) && !str_contains($text, 'no tasks');
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
            exec('taskkill /PID ' . $pid . ' /T /F');
        } else {
            exec('kill ' . $pid . ' 2>/dev/null');
        }
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
            'model' => $this->modelName(),
        ];
        @file_put_contents($this->statusFile, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
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
