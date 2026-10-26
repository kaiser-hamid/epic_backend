<?php

namespace Modules\Shared\Auth\Actions;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Shared\Audit\Contracts\AuditLogger;
use Modules\Shared\Audit\Enums\AuditAction;
use Modules\Shared\Auth\Exceptions\SessionNotFound;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Auth\Services\SessionManager;

/**
 * Signs one of the user's own devices out. The current session is ended with logout instead.
 */
final class RevokeSession
{
    public function __construct(
        private readonly Request $request,
        private readonly SessionManager $sessions,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @throws SessionNotFound
     */
    public function handle(User $user, string $sessionId): void
    {
        if ($sessionId === $this->request->session()->getId()) {
            throw new SessionNotFound;
        }

        DB::transaction(function () use ($user, $sessionId): void {
            $this->sessions->revoke($user, $sessionId);

            $this->auditLogger->record(AuditAction::SessionRevoked, $user, newValues: ['revoked' => 1]);
        });
    }
}
