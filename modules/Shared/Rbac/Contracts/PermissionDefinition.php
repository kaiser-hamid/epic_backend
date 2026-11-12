<?php

namespace Modules\Shared\Rbac\Contracts;

/**
 * Implemented by each module's backed permission enum (e.g. `bb.unit.issue`). Values are the permission names.
 * The module (key, label, order) is the top-level heading of the admin permission screen; it is layout only.
 */
interface PermissionDefinition
{
    /**
     * Module key, e.g. "blood-bank", "billing", "access". Role templates belong to exactly one module.
     */
    public function module(): string;

    /**
     * Module heading shown in the admin permission screen, e.g. "Blood Bank".
     */
    public function moduleLabel(): string;

    /**
     * Position of the module heading (ascending).
     */
    public function moduleOrder(): int;

    /**
     * True when only some branches run this module (switched on per branch in `sh_branch_modules`). In a
     * branch without it, its permissions cannot be granted and allow nothing, not even for a super admin.
     * False means every branch has the module.
     */
    public function isOptionalModule(): bool;

    /**
     * Functional group within the module, e.g. "Samples", "Users".
     */
    public function group(): string;

    public function description(): string;
}
