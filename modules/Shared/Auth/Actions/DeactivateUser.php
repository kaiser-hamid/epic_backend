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

/**
 * Blocks the account and signs it out of every device. Grants are kept so reactivation restores access.
 */
final class DeactivateUser
{
    public function __construct(
        private readonly AccountGuard $accountGuard,
        private readonly SessionManager $sessions,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @throws SuperAdminProtected|CannotChangeOwnAccount
     */
    public function handle(User $actor, User $user): User
    {
        $this->accountGuard->ensureCanManage($actor, $user);

        return DB::transaction(function () use ($user): User {
            if ($user->is_active) {
                $user->forceFill(['is_active' => false])->save();
                $this->auditLogger->record(AuditAction::UserDeactivated, $user, ['is_active' => true], ['is_active' => false]);
            }

            $this->sessions->revokeAll($user);

            return $user;
        });
    }
}
