<?php

namespace Modules\Shared\Branch\Services;

use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Branch\Models\BranchModule;

/**
 * Replaces the optional modules a branch runs. Callers own the transaction and the audit entry.
 */
final class BranchModuleWriter
{
    /**
     * @param  list<string>  $modules
     */
    public function replace(Branch $branch, array $modules): void
    {
        $modules = $this->sorted($modules);

        BranchModule::query()->where('branch_id', $branch->getKey())->whereNotIn('module', $modules)->delete();

        foreach ($modules as $module) {
            BranchModule::query()->firstOrCreate(['branch_id' => $branch->getKey(), 'module' => $module]);
        }
    }

    /**
     * @param  list<string>  $modules
     * @return list<string>
     */
    public function sorted(array $modules): array
    {
        $modules = array_values(array_unique($modules));
        sort($modules);

        return $modules;
    }
}
