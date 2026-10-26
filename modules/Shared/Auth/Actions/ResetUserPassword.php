<?php

namespace Modules\Shared\Auth\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Shared\Audit\Contracts\AuditLogger;
use Modules\Shared\Audit\Enums\AuditAction;
use Modules\Shared\Auth\Data\ResetPasswordData;
use Modules\Shared\Auth\Exceptions\CannotChangeOwnAccount;
use Modules\Shared\Auth\Exceptions\SuperAdminProtected;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Auth\Services\AccountGuard;
use Modules\Shared\Auth\Services\SessionManager;

/**
 * Sets a temporary password the user must change on next login, clears any lock, and signs them out
 * everywhere. Admins change their own password through ChangePassword instead.
 */
final class ResetUserPassword
{
    public function __construct(
        private readonly AccountGuard $accountGuard,
        private readonly SessionManager $sessions,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @throws SuperAdminProtected|CannotChangeOwnAccount
     */
    public function handle(User $actor, User $user, ResetPasswordData $data): void
    {
        $this->accountGuard->ensureCanManage($actor, $user);

        DB::transaction(function () use ($user, $data): void {
            $user->forceFill([
                'password' => $data->password,
                'must_change_password' => true,
                'password_changed_at' => now(),
                'failed_login_attempts' => 0,
                'locked_until' => null,
            ])->save();

            $this->sessions->revokeAll($user);

            $this->auditLogger->record(AuditAction::UserPasswordReset, $user, newValues: ['must_change_password' => true]);
        });
    }
}
