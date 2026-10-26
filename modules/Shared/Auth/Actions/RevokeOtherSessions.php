<?php

namespace Modules\Shared\Auth\Actions;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Shared\Audit\Contracts\AuditLogger;
use Modules\Shared\Audit\Enums\AuditAction;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Auth\Services\SessionManager;

/**
 * Signs the user out everywhere except the device making this request.
 */
final class RevokeOtherSessions
{
    public function __construct(
        private readonly Request $request,
        private readonly SessionManager $sessions,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(User $user): int
    {
        return DB::transaction(function () use ($user): int {
            $revoked = $this->sessions->revokeAllExcept($user, $this->request->session()->getId());

            $this->auditLogger->record(AuditAction::SessionRevoked, $user, newValues: ['revoked' => $revoked]);

            return $revoked;
        });
    }
}
