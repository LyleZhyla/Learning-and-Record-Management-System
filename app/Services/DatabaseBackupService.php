<?php

namespace App\Services;

use DateTimeInterface;
use Generator;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DatabaseBackupService
{
    public function information(): array
    {
        $connection = DB::connection();
        $database = (string) $connection->getDatabaseName();

        return [
            'driver' => $connection->getDriverName(),
            'name' => $connection->getDriverName() === 'sqlite' ? basename($database) : $database,
            'tables' => count($this->tables($connection)),
        ];
    }

    public function filename(): string
    {
        return 'snapie-database-'.now()->format('Y-m-d_His').'.sql';
    }

    public function stream(): Generator
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb', 'sqlite'], true)) {
            throw new RuntimeException("Database backups are not available for the {$driver} driver.");
        }

        yield '-- SNAPIE database backup'.PHP_EOL;
        yield '-- Generated: '.now()->toIso8601String().PHP_EOL;
        yield '-- Driver: '.$driver.PHP_EOL.PHP_EOL;
        yield $driver === 'sqlite'
            ? 'PRAGMA foreign_keys=OFF;'.PHP_EOL.'BEGIN TRANSACTION;'.PHP_EOL.PHP_EOL
            : 'SET NAMES utf8mb4;'.PHP_EOL.'SET FOREIGN_KEY_CHECKS=0;'.PHP_EOL.'START TRANSACTION;'.PHP_EOL.PHP_EOL;

        foreach ($this->tables($connection) as $table) {
            $identifier = $this->quoteIdentifier($table['name'], $driver);
            yield 'DROP TABLE IF EXISTS '.$identifier.';'.PHP_EOL;
            yield rtrim($table['create'], "; \t\n\r\0\x0B").';'.PHP_EOL.PHP_EOL;
            yield from $this->tableRows($connection, $table['name'], $driver);
        }

        yield 'COMMIT;'.PHP_EOL;
        yield $driver === 'sqlite' ? 'PRAGMA foreign_keys=ON;'.PHP_EOL : 'SET FOREIGN_KEY_CHECKS=1;'.PHP_EOL;
    }

    private function tables(Connection $connection): array
    {
        if ($connection->getDriverName() === 'sqlite') {
            return collect($connection->select("SELECT name, sql FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"))
                ->filter(fn ($table) => filled($table->sql))
                ->map(fn ($table) => ['name' => $table->name, 'create' => $table->sql])
                ->values()
                ->all();
        }

        return collect($connection->select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"))
            ->map(function ($row) use ($connection): array {
                $name = (string) array_values((array) $row)[0];
                $createRow = (array) $connection->selectOne('SHOW CREATE TABLE '.$this->quoteIdentifier($name, 'mysql'));

                return ['name' => $name, 'create' => (string) array_values($createRow)[1]];
            })
            ->all();
    }

    private function tableRows(Connection $connection, string $table, string $driver): Generator
    {
        $batch = [];
        $columns = [];

        foreach ($connection->table($table)->cursor() as $row) {
            $values = (array) $row;
            if ($columns === []) {
                $columns = array_keys($values);
            }
            $batch[] = '('.collect($values)->map(fn ($value) => $this->quoteValue($connection, $value))->implode(', ').')';

            if (count($batch) >= 100) {
                yield $this->insertStatement($table, $columns, $batch, $driver);
                $batch = [];
            }
        }

        if ($batch !== []) {
            yield $this->insertStatement($table, $columns, $batch, $driver);
        }
    }

    private function insertStatement(string $table, array $columns, array $rows, string $driver): string
    {
        $columnList = collect($columns)->map(fn ($column) => $this->quoteIdentifier($column, $driver))->implode(', ');

        return 'INSERT INTO '.$this->quoteIdentifier($table, $driver).' ('.$columnList.') VALUES'.PHP_EOL
            .implode(','.PHP_EOL, $rows).';'.PHP_EOL.PHP_EOL;
    }

    private function quoteIdentifier(string $identifier, string $driver): string
    {
        return $driver === 'sqlite'
            ? '"'.str_replace('"', '""', $identifier).'"'
            : '`'.str_replace('`', '``', $identifier).'`';
    }

    private function quoteValue(Connection $connection, mixed $value): string
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
        if ($value instanceof DateTimeInterface) {
            $value = $value->format('Y-m-d H:i:s');
        }
        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }

        $quoted = $connection->getPdo()->quote((string) $value);
        if ($quoted === false) {
            throw new RuntimeException('A database value could not be safely exported.');
        }

        return $quoted;
    }
}
