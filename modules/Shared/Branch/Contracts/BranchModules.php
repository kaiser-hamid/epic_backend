<?php

namespace Modules\Shared\Branch\Contracts;

/**
 * Which optional modules (e.g. "blood-bank") a branch runs. Modules every branch has are never listed, so
 * ask only about a module that is optional.
 */
interface BranchModules
{
    /**
     * @return list<string>
     */
    public function enabledIn(int $branchId): array;

    public function isEnabled(int $branchId, string $module): bool;
}
