<?php

namespace Modules\Shared\Branch\Services;

use Modules\Shared\Branch\Contracts\BranchModules;
use Modules\Shared\Branch\Models\BranchModule;

final class DatabaseBranchModules implements BranchModules
{
    public function enabledIn(int $branchId): array
    {
        return BranchModule::query()->where('branch_id', $branchId)->orderBy('module')->pluck('module')->all();
    }

    public function isEnabled(int $branchId, string $module): bool
    {
        return BranchModule::query()->where('branch_id', $branchId)->where('module', $module)->exists();
    }
}
