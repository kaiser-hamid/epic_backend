<?php

namespace Modules\Shared\Rbac\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Catalog row mirrored from the code-defined permission enums by `permissions:sync`. Never edited by users.
 */
#[Table('sh_permissions')]
#[Fillable(['name', 'module', 'module_label', 'module_order', 'module_is_optional', 'group', 'description'])]
final class Permission extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'module_is_optional' => 'boolean',
        ];
    }
}
