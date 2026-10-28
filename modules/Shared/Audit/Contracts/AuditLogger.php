<?php

namespace Modules\Shared\Audit\Contracts;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Modules\Shared\Audit\Models\AuditLog;
use Modules\Shared\Auth\Models\User;

/**
 * Writes the append-only audit trail. The actor defaults to the authenticated user; IP, user agent and
 * the session branch are taken from the current request. Never pass passwords or secrets in the values.
 * Each module passes its own action enum (Shared uses `AuditAction`) with module-prefixed values.
 */
interface AuditLogger
{
    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function record(BackedEnum $action, ?Model $subject = null, array $oldValues = [], array $newValues = [], ?User $actor = null): AuditLog;
}
