<?php

namespace Modules\Shared\Auth\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Shared\Audit\Contracts\AuditLogger;
use Modules\Shared\Audit\Enums\AuditAction;
use Modules\Shared\Auth\Exceptions\CannotChangeOwnAccount;
use Modules\Shared\Auth\Exceptions\SuperAdminProtected;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Auth\Services\AccountGuard;

final class UnlockUser
{
    public function __construct(
        private readonly AccountGuard $accountGuard,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @throws SuperAdminProtected|CannotChangeOwnAccount
     */
    public function handle(User $actor, User $user): User
    {
        $this->accountGuard->ensureCanManage($actor, $user);

        return DB::transaction(function () use ($user): User {
            $lockedUntil = $user->locked_until?->toIso8601String();

            $user->forceFill(['failed_login_attempts' => 0, 'locked_until' => null])->save();

            $this->auditLogger->record(AuditAction::UserUnlocked, $user, ['locked_until' => $lockedUntil], ['locked_until' => null]);

            return $user;
        });
    }
}
