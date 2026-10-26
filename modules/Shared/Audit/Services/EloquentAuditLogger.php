<?php

namespace Modules\Shared\Audit\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Modules\Shared\Audit\Contracts\AuditLogger;
use Modules\Shared\Audit\Enums\AuditAction;
use Modules\Shared\Audit\Models\AuditLog;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Branch\Contracts\CurrentBranch;

final class EloquentAuditLogger implements AuditLogger
{
    public function __construct(
        private readonly Request $request,
        private readonly CurrentBranch $currentBranch,
    ) {}

    public function record(AuditAction $action, ?Model $subject = null, array $oldValues = [], array $newValues = [], ?User $actor = null): AuditLog
    {
        $actor ??= $this->request->user();

        return AuditLog::create([
            'actor_id' => $actor?->getKey(),
            'branch_id' => $this->currentBranch->id(),
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => $this->request->ip(),
            'user_agent' => mb_substr((string) $this->request->userAgent(), 0, 500),
        ]);
    }
}
