<?php

namespace Modules\Shared\Auth\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Shared\Audit\Contracts\AuditLogger;
use Modules\Shared\Audit\Enums\AuditAction;
use Modules\Shared\Auth\Exceptions\CannotChangeOwnAccount;
use Modules\Shared\Auth\Exceptions\SuperAdminProtected;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Auth\Services\AccountGuard;

final class ActivateUser
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
            if (! $user->is_active) {
                $user->forceFill(['is_active' => true])->save();
                $this->auditLogger->record(AuditAction::UserActivated, $user, ['is_active' => false], ['is_active' => true]);
            }

            return $user;
        });
    }
}
