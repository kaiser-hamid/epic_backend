<?php

namespace Modules\Shared\Auth\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Shared\Audit\Contracts\AuditLogger;
use Modules\Shared\Audit\Enums\AuditAction;
use Modules\Shared\Auth\Exceptions\CannotChangeOwnAccount;
use Modules\Shared\Auth\Exceptions\SuperAdminProtected;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Auth\Services\AccountGuard;
use Modules\Shared\Auth\Services\SessionManager;

final class ForceLogoutUser
{
    public function __construct(
        private readonly AccountGuard $accountGuard,
        private readonly SessionManager $sessions,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @throws SuperAdminProtected|CannotChangeOwnAccount
     */
    public function handle(User $actor, User $user): int
    {
        $this->accountGuard->ensureCanManage($actor, $user);

        return DB::transaction(function () use ($user): int {
            $revoked = $this->sessions->revokeAll($user);

            $this->auditLogger->record(AuditAction::UserForcedLogout, $user, newValues: ['revoked' => $revoked]);

            return $revoked;
        });
    }
}
