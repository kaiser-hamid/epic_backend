<?php

namespace Modules\Shared\Rbac\Contracts;

use BackedEnum;

/**
 * Implemented by each module's backed enum of default role templates; the value is the template name.
 * `permissions:sync` creates a missing default once. After that the template is the admins' to edit or delete.
 */
interface RoleTemplateDefinition
{
    public function description(): string;

    /**
     * All from one module; that module becomes the template's module.
     *
     * @return list<PermissionDefinition&BackedEnum>
     */
    public function permissions(): array;
}
