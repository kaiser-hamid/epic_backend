<?php

namespace Modules\Shared\Audit\Contracts;

use Illuminate\Database\Eloquent\Model;
use Modules\Shared\Audit\Enums\AuditAction;
use Modules\Shared\Audit\Models\AuditLog;
use Modules\Shared\Auth\Models\User;

/**
 * Writes the append-only audit trail. The actor defaults to the authenticated user; IP, user agent and
 * the session branch are taken from the current request. Never pass passwords or secrets in the values.
 */
interface AuditLogger
{
    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function record(AuditAction $action, ?Model $subject = null, array $oldValues = [], array $newValues = [], ?User $actor = null): AuditLog;
}
