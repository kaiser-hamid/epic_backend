<?php

namespace Modules\Shared\Support\Console;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Modules\Shared\Support\Actions\ResetTransactionalData;

/**
 * IT/Developer tool to wipe test or go-live-rehearsal data while keeping the system's configuration.
 */
final class ResetTransactionalDataCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'data:reset
        {--include-audit : Also clear the audit log (it is append-only otherwise)}
        {--force : Skip the confirmation, and allow running in production}';

    protected $description = 'Clear all user-entered records (invoices, patients, cases, units, stock movements, print logs, number counters) and keep configuration';

    public function handle(ResetTransactionalData $resetTransactionalData): int
    {
        $includeAudit = (bool) $this->option('include-audit');
        $tables = $resetTransactionalData->tables($includeAudit);

        $this->warn('This permanently deletes every row in:');
        $this->line('  '.implode(', ', $tables));
        $this->line('Users, branches, permissions, doctors, the test catalog and other master data are kept.');

        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Clear these tables now?')) {
            $this->line('Nothing was changed.');

            return self::FAILURE;
        }

        $cleared = $resetTransactionalData->handle($includeAudit);

        $this->info('Cleared '.count($cleared).' tables. Invoice and case numbers start again from 1.');

        return self::SUCCESS;
    }
}
