<?php

namespace Modules\Shared\Auth\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Shared\Audit\Contracts\AuditLogger;
use Modules\Shared\Audit\Enums\AuditAction;
use Modules\Shared\Auth\Exceptions\LastSuperAdmin;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Auth\Services\SessionManager;

/**
 * Grants or revokes super admin. IT/Developer only: reachable from the `auth:super-admin` console command,
 * never from the API. Revoking signs the user out so the new rights apply immediately.
 */
final class SetSuperAdmin
{
    public function __construct(
        private readonly SessionManager $sessions,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @throws LastSuperAdmin
     */
    public function handle(User $user, bool $isSuperAdmin): User
    {
        return DB::transaction(function () use ($user, $isSuperAdmin): User {
            if ($user->is_super_admin === $isSuperAdmin) {
                return $user;
            }

            if (! $isSuperAdmin && $this->isLastActiveSuperAdmin($user)) {
                throw new LastSuperAdmin;
            }

            $user->forceFill(['is_super_admin' => $isSuperAdmin])->save();

            if (! $isSuperAdmin) {
                $this->sessions->revokeAll($user);
            }

            $this->auditLogger->record(
                $isSuperAdmin ? AuditAction::SuperAdminGranted : AuditAction::SuperAdminRevoked,
                $user,
                ['is_super_admin' => ! $isSuperAdmin],
                ['is_super_admin' => $isSuperAdmin],
            );

            return $user;
        });
    }

    private function isLastActiveSuperAdmin(User $user): bool
    {
        return ! User::query()
            ->where('is_super_admin', true)
            ->where('is_active', true)
            ->whereKeyNot($user->getKey())
            ->lockForUpdate()
            ->exists();
    }
}
