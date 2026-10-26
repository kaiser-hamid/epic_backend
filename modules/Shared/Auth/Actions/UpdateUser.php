<?php

namespace Modules\Shared\Auth\Actions;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Modules\Shared\Audit\Contracts\AuditLogger;
use Modules\Shared\Audit\Enums\AuditAction;
use Modules\Shared\Auth\Data\UpdateUserData;
use Modules\Shared\Auth\Exceptions\SuperAdminProtected;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Auth\Services\AccountGuard;

final class UpdateUser
{
    public function __construct(
        private readonly AccountGuard $accountGuard,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @throws SuperAdminProtected
     */
    public function handle(User $actor, User $user, UpdateUserData $data): User
    {
        $this->accountGuard->ensureNotProtected($actor, $user);

        return DB::transaction(function () use ($user, $data): User {
            $user->fill(Arr::only($data->toArray(), ['employee_id', 'name', 'username', 'designation', 'phone']));
            $changes = $user->getDirty();
            $original = Arr::only($user->getOriginal(), array_keys($changes));

            if ($changes !== []) {
                $user->save();
                $this->auditLogger->record(AuditAction::UserUpdated, $user, $original, $changes);
            }

            return $user;
        });
    }
}
