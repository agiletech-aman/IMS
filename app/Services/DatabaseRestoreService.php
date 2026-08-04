<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\Process;

class DatabaseRestoreService
{
    public function __construct(private readonly DatabaseBackupService $database) {}

    public function import(string $sqlPath): string
    {
        $driver = DB::connection()->getDriverName();
        if (
            in_array($driver, ['mysql', 'mariadb'], true)
            && $this->database->shellAvailable()
            && config('backup.protected_restore_tables', []) === []
        ) {
            try {
                $this->importWithMysql($sqlPath);

                return 'mysql';
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        $this->importWithPhp($sqlPath);

        return 'php';
    }

    private function importWithMysql(string $sqlPath): void
    {
        $config = DB::connection()->getConfig();
        $handle = fopen($sqlPath, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to read the SQL backup.');
        }

        try {
            $process = new Process([
                (string) config('backup.mysql_binary', 'mysql'),
                '--host='.(string) ($config['host'] ?? '127.0.0.1'),
                '--port='.(string) ($config['port'] ?? '3306'),
                '--user='.(string) ($config['username'] ?? ''),
                '--default-character-set=utf8mb4',
                (string) $config['database'],
            ], base_path(), ['MYSQL_PWD' => (string) ($config['password'] ?? '')], $handle, 3600);
            $process->run();
            if (! $process->isSuccessful()) {
                throw new RuntimeException('mysql restore failed: '.mb_strimwidth(trim($process->getErrorOutput()), 0, 500));
            }
        } finally {
            fclose($handle);
        }
    }

    private function importWithPhp(string $sqlPath): void
    {
        $sql = file_get_contents($sqlPath);
        if ($sql === false || trim($sql) === '') {
            throw new RuntimeException('SQL backup is empty or unreadable.');
        }

        $pdo = DB::connection()->getPdo();
        foreach ($this->splitStatements($sql) as $statement) {
            $trimmed = trim($statement);
            $control = strtoupper(rtrim($trimmed, ';'));
            if (in_array($control, ['BEGIN', 'BEGIN TRANSACTION', 'COMMIT'], true)) {
                continue;
            }
            if ($this->targetsProtectedTable($trimmed)) {
                continue;
            }
            if ($trimmed !== '' && ! str_starts_with($trimmed, '--')) {
                $pdo->exec($trimmed);
            }
        }
    }

    private function splitStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $quote = null;
        $length = strlen($sql);
        $lineComment = false;
        $blockComment = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($lineComment) {
                if ($char === "\n") {
                    $lineComment = false;
                    $buffer .= $char;
                }

                continue;
            }
            if ($blockComment) {
                if ($char === '*' && $next === '/') {
                    $blockComment = false;
                    $i++;
                }

                continue;
            }
            if ($quote === null && $char === '-' && $next === '-') {
                $lineComment = true;
                $i++;

                continue;
            }
            if ($quote === null && $char === '/' && $next === '*') {
                $blockComment = true;
                $i++;

                continue;
            }
            if ($quote === null && in_array($char, ["'", '"', '`'], true)) {
                $quote = $char;
                $buffer .= $char;

                continue;
            }
            if ($quote !== null && $char === $quote) {
                if ($next === $quote) {
                    $buffer .= $char.$next;
                    $i++;

                    continue;
                }
                $backslashes = 0;
                for ($j = $i - 1; $j >= 0 && $sql[$j] === '\\'; $j--) {
                    $backslashes++;
                }
                if ($backslashes % 2 === 0) {
                    $quote = null;
                }
                $buffer .= $char;

                continue;
            }
            if ($quote === null && $char === ';') {
                if (trim($buffer) !== '') {
                    $statements[] = $buffer;
                }
                $buffer = '';

                continue;
            }
            $buffer .= $char;
        }

        if (trim($buffer) !== '') {
            $statements[] = $buffer;
        }

        return $statements;
    }

    private function targetsProtectedTable(string $statement): bool
    {
        $protected = array_map('strtolower', (array) config('backup.protected_restore_tables', []));
        if ($protected === []) {
            return false;
        }

        preg_match_all(
            '/\b(?:TABLES?|INTO)\s+(?:IF\s+(?:NOT\s+)?EXISTS\s+)?[`"]?([A-Za-z0-9_]+)[`"]?/i',
            $statement,
            $matches,
        );

        return collect($matches[1] ?? [])
            ->map(fn (string $table) => strtolower($table))
            ->intersect($protected)
            ->isNotEmpty();
    }
}
