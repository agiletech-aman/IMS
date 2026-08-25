<?php

namespace App\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class DatabaseBackupService
{
    public function __construct(private readonly CentreContextService $centreContext) {}

    public function export(string $destination): string
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $centre = $this->centreContext->selected();

        if (in_array($driver, ['mysql', 'mariadb'], true) && $this->mysqlDumpAvailable()) {
            try {
                $this->exportWithMysqlDump($destination, $centre);

                return 'mysqldump';
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        $this->exportWithPhp($connection, $destination, $centre);

        return 'php';
    }

    public function shellAvailable(): bool
    {
        if (! config('backup.shell_enabled')) {
            return false;
        }

        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return function_exists('proc_open') && ! in_array('proc_open', $disabled, true);
    }

    public function mysqlDumpAvailable(): bool
    {
        if (! $this->shellAvailable()) {
            return false;
        }

        try {
            $process = new Process([(string) config('backup.mysqldump_binary', 'mysqldump'), '--version']);
            $process->setTimeout(5);
            $process->run();

            return $process->isSuccessful();
        } catch (Throwable) {
            return false;
        }
    }

    private function exportWithMysqlDump(string $destination, ?string $centre): void
    {
        if ($centre === null) {
            $this->exportWithMysqlDumpWhole($destination);

            return;
        }

        $this->exportWithMysqlDumpForCentre($destination, $centre);
    }

    private function exportWithMysqlDumpWhole(string $destination): void
    {
        $config = DB::connection()->getConfig();
        $database = (string) $config['database'];
        $command = [
            (string) config('backup.mysqldump_binary', 'mysqldump'),
            '--host='.(string) ($config['host'] ?? '127.0.0.1'),
            '--port='.(string) ($config['port'] ?? '3306'),
            '--user='.(string) ($config['username'] ?? ''),
            '--single-transaction',
            '--quick',
            '--skip-comments',
            '--default-character-set=utf8mb4',
            '--result-file='.$destination,
        ];

        foreach ($this->excludedTables() as $table) {
            $command[] = "--ignore-table={$database}.{$table}";
        }
        $command[] = $database;

        $process = new Process($command, base_path(), [
            'MYSQL_PWD' => (string) ($config['password'] ?? ''),
        ]);
        $process->setTimeout(3600);
        $process->run();

        if (! $process->isSuccessful() || ! is_file($destination) || filesize($destination) === 0) {
            File::delete($destination);
            $error = trim($process->getErrorOutput()) ?: 'mysqldump did not produce an output file.';
            throw new RuntimeException('mysqldump failed: '.mb_strimwidth($error, 0, 500));
        }
    }

    /**
     * Dumps schema for every table, then dumps data table-by-table so that
     * centre-scoped tables can be restricted to the selected centre while
     * tables without a centre column (shared/reference data) still export
     * in full.
     */
    private function exportWithMysqlDumpForCentre(string $destination, string $centre): void
    {
        $config = DB::connection()->getConfig();
        $database = (string) $config['database'];
        $binary = (string) config('backup.mysqldump_binary', 'mysqldump');
        $env = ['MYSQL_PWD' => (string) ($config['password'] ?? '')];
        $baseArgs = [
            '--host='.(string) ($config['host'] ?? '127.0.0.1'),
            '--port='.(string) ($config['port'] ?? '3306'),
            '--user='.(string) ($config['username'] ?? ''),
            '--skip-comments',
            '--default-character-set=utf8mb4',
        ];

        $tables = $this->tables(DB::connection());
        $centreTables = $this->centreScopedTables($tables);

        $schemaCommand = array_merge([$binary], $baseArgs, [
            '--no-data',
            '--result-file='.$destination,
        ], array_map(
            fn (string $table) => "--ignore-table={$database}.{$table}",
            $this->excludedTables(),
        ), [$database]);

        $schemaProcess = new Process($schemaCommand, base_path(), $env);
        $schemaProcess->setTimeout(600);
        $schemaProcess->run();

        if (! $schemaProcess->isSuccessful()) {
            File::delete($destination);
            $error = trim($schemaProcess->getErrorOutput()) ?: 'mysqldump did not produce a schema file.';
            throw new RuntimeException('mysqldump failed: '.mb_strimwidth($error, 0, 500));
        }

        $handle = fopen($destination, 'ab');
        if ($handle === false) {
            throw new RuntimeException('Unable to append to the database export file.');
        }

        try {
            foreach ($tables as $table) {
                $dataCommand = array_merge([$binary], $baseArgs, [
                    '--single-transaction',
                    '--quick',
                    '--no-create-info',
                    '--skip-add-locks',
                ]);

                if (in_array($table, $centreTables, true)) {
                    $dataCommand[] = "--where=centre = '{$centre}'";
                }

                $dataCommand[] = $database;
                $dataCommand[] = $table;

                $dataProcess = new Process($dataCommand, base_path(), $env);
                $dataProcess->setTimeout(3600);
                $dataProcess->run();

                if (! $dataProcess->isSuccessful()) {
                    $error = trim($dataProcess->getErrorOutput()) ?: "mysqldump failed while exporting table {$table}.";
                    throw new RuntimeException('mysqldump failed: '.mb_strimwidth($error, 0, 500));
                }

                fwrite($handle, $dataProcess->getOutput()."\n");
            }
        } finally {
            fclose($handle);
        }

        if (! is_file($destination) || filesize($destination) === 0) {
            File::delete($destination);
            throw new RuntimeException('mysqldump did not produce an output file.');
        }
    }

    private function exportWithPhp(ConnectionInterface $connection, string $destination, ?string $centre): void
    {
        $handle = fopen($destination, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Unable to create the database export file.');
        }

        try {
            fwrite($handle, "-- IMS database backup\n");
            fwrite($handle, '-- Generated: '.now()->toIso8601String()."\n\n");
            fwrite($handle, $connection->getDriverName() === 'sqlite'
                ? "PRAGMA foreign_keys=OFF;\nBEGIN TRANSACTION;\n\n"
                : "SET FOREIGN_KEY_CHECKS=0;\n\n");

            $tables = $this->tables($connection);
            $centreTables = $this->centreScopedTables($tables);

            foreach ($tables as $table) {
                $identifier = $this->quoteIdentifier($table, $connection->getDriverName());
                $schema = $this->createStatement($connection, $table);
                fwrite($handle, "DROP TABLE IF EXISTS {$identifier};\n{$schema};\n\n");

                $query = $connection->table($table);
                if ($centre !== null && in_array($table, $centreTables, true)) {
                    $query->where('centre', $centre);
                }

                foreach ($query->cursor() as $row) {
                    $values = (array) $row;
                    if ($values === []) {
                        continue;
                    }
                    $columns = implode(', ', array_map(
                        fn (string $column) => $this->quoteIdentifier($column, $connection->getDriverName()),
                        array_keys($values),
                    ));
                    $encoded = implode(', ', array_map(
                        fn ($value) => $this->quoteValue($connection, $value),
                        array_values($values),
                    ));
                    fwrite($handle, "INSERT INTO {$identifier} ({$columns}) VALUES ({$encoded});\n");
                }
                fwrite($handle, "\n");
            }

            fwrite($handle, $connection->getDriverName() === 'sqlite'
                ? "COMMIT;\nPRAGMA foreign_keys=ON;\n"
                : "SET FOREIGN_KEY_CHECKS=1;\n");
        } finally {
            fclose($handle);
        }

        if (! is_file($destination) || filesize($destination) === 0) {
            throw new RuntimeException('The PHP database exporter produced an empty file.');
        }
    }

    /** @param string[] $tables */
    private function centreScopedTables(array $tables): array
    {
        return array_values(array_filter(
            $tables,
            fn (string $table) => Schema::hasColumn($table, 'centre'),
        ));
    }

    private function tables(ConnectionInterface $connection): array
    {
        if ($connection->getDriverName() === 'sqlite') {
            $rows = $connection->select(
                "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
            );

            return collect($rows)->pluck('name')->diff($this->excludedTables())->values()->all();
        }

        $rows = $connection->select('SHOW FULL TABLES WHERE Table_type = ?', ['BASE TABLE']);
        $tables = array_map(fn ($row) => array_values((array) $row)[0], $rows);

        return array_values(array_diff($tables, $this->excludedTables()));
    }

    private function createStatement(ConnectionInterface $connection, string $table): string
    {
        if ($connection->getDriverName() === 'sqlite') {
            $row = $connection->selectOne(
                "SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?",
                [$table],
            );

            return (string) $row->sql;
        }

        $row = (array) $connection->selectOne(
            'SHOW CREATE TABLE '.$this->quoteIdentifier($table, $connection->getDriverName())
        );

        return (string) array_values($row)[1];
    }

    private function quoteIdentifier(string $identifier, string $driver): string
    {
        if (! preg_match('/\A[A-Za-z0-9_]+\z/', $identifier)) {
            throw new RuntimeException('Unsafe database identifier encountered.');
        }

        return $driver === 'sqlite' ? '"'.$identifier.'"' : '`'.$identifier.'`';
    }

    private function quoteValue(ConnectionInterface $connection, mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $connection->getPdo()->quote((string) $value);
    }

    private function excludedTables(): array
    {
        return array_values(array_filter((array) config('backup.excluded_database_tables', [])));
    }
}
