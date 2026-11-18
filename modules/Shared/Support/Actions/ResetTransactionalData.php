<?php

namespace Modules\Shared\Support\Actions;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Schema;
use Modules\Shared\Support\Contracts\TransactionalData;

/**
 * Clears every user-entered business record of every module (invoices, patients, cases, units, stock movements,
 * print logs and the number counters) and keeps configuration: users, branches, permissions, doctors, the test
 * catalog and each module's master data. Identity columns restart at 1.
 *
 * It is all or nothing: one transaction, one statement on PostgreSQL so foreign keys between modules'
 * tables never block it.
 */
final class ResetTransactionalData
{
    /**
     * @param  iterable<TransactionalData>  $sources
     */
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly iterable $sources,
    ) {}

    /**
     * @return list<string> the tables that were cleared
     */
    public function handle(bool $includeAuditLog = false): array
    {
        $tables = $this->tables($includeAuditLog);

        $this->connection->transaction(function () use ($tables): void {
            $this->clear($tables);

            foreach ($this->sources as $source) {
                $source->afterReset();
            }
        });

        return $tables;
    }

    /**
     * Tables the reset would clear, in the order modules declared them.
     *
     * @return list<string>
     */
    public function tables(bool $includeAuditLog = false): array
    {
        $tables = [];

        foreach ($this->sources as $source) {
            array_push($tables, ...$source->tables());
        }

        if ($includeAuditLog) {
            $tables[] = 'sh_audit_logs';
        }

        return array_values(array_filter(
            array_unique($tables),
            fn (string $table): bool => Schema::hasTable($table),
        ));
    }

    /**
     * @param  list<string>  $tables
     */
    private function clear(array $tables): void
    {
        if ($tables === []) {
            return;
        }

        if ($this->connection->getDriverName() === 'pgsql') {
            $list = implode(', ', array_map(fn (string $table): string => '"'.$table.'"', $tables));

            $this->connection->statement("TRUNCATE TABLE {$list} RESTART IDENTITY");

            return;
        }

        // Any other driver (the SQLite test database): delete row by row and check foreign keys only at commit.
        $this->connection->statement('PRAGMA defer_foreign_keys = ON');

        foreach ($tables as $table) {
            $this->connection->table($table)->delete();
        }
    }
}
