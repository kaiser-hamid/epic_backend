<?php

namespace Modules\Shared\Rbac\Contracts;

/**
 * Implemented by each module's backed permission enum (e.g. `bb.unit.issue`). Values are the permission names.
 */
interface PermissionDefinition
{
    /**
     * Module key shown in the admin permission tree, e.g. "shared", "blood-bank".
     */
    public function module(): string;

    /**
     * Functional group shown in the admin permission tree, e.g. "Users", "Cash".
     */
    public function group(): string;

    public function description(): string;
}
