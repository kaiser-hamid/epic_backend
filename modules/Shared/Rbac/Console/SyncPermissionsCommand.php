<?php

namespace Modules\Shared\Rbac\Console;

use Illuminate\Console\Command;
use Modules\Shared\Rbac\Actions\SyncPermissionCatalog;

final class SyncPermissionsCommand extends Command
{
    protected $signature = 'permissions:sync';

    protected $description = 'Sync the permission catalog from every module\'s permission enum';

    public function handle(SyncPermissionCatalog $syncPermissionCatalog): int
    {
        $result = $syncPermissionCatalog->handle();

        $this->info("Synced {$result['upserted']} permissions.");

        if ($result['deleted'] !== []) {
            $this->line('Deleted unused: '.implode(', ', $result['deleted']));
        }

        if ($result['kept'] !== []) {
            $this->warn('No longer in code but still assigned (kept): '.implode(', ', $result['kept']));
        }

        return self::SUCCESS;
    }
}
