<?php

namespace Modules\Shared\Rbac\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Shared\Audit\Contracts\AuditLogger;
use Modules\Shared\Audit\Enums\AuditAction;
use Modules\Shared\Rbac\Models\Role;

/**
 * Soft-deletes a template. No user loses anything, because roles never grant access.
 */
final class DeleteRole
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Role $role): void
    {
        DB::transaction(function () use ($role): void {
            $role->delete();

            $this->auditLogger->record(AuditAction::RoleDeleted, $role, ['name' => $role->name]);
        });
    }
}
