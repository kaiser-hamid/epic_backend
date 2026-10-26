<?php

namespace Modules\Shared\Auth\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Modules\Shared\Auth\Exceptions\SessionNotFound;
use Modules\Shared\Auth\Models\User;

/**
 * Lists and revokes a user's sessions in the database session store. Deleting a row logs that device out
 * on its next request.
 */
final class SessionManager
{
    public function __construct(private readonly ConnectionInterface $db) {}

    /**
     * @return Collection<int, object{id: string, branch_id: int|null, ip_address: string|null, user_agent: string|null, last_activity: int}>
     */
    public function forUser(User $user): Collection
    {
        return $this->sessionsOf($user)
            ->orderByDesc('last_activity')
            ->get(['id', 'branch_id', 'ip_address', 'user_agent', 'last_activity']);
    }

    /**
     * @throws SessionNotFound
     */
    public function revoke(User $user, string $sessionId): void
    {
        $deleted = $this->sessionsOf($user)->where('id', $sessionId)->delete();

        if ($deleted === 0) {
            throw new SessionNotFound;
        }
    }

    public function revokeAllExcept(User $user, ?string $keepSessionId): int
    {
        return $this->sessionsOf($user)
            ->when($keepSessionId !== null, fn (Builder $query) => $query->where('id', '!=', $keepSessionId))
            ->delete();
    }

    public function revokeAll(User $user): int
    {
        return $this->revokeAllExcept($user, null);
    }

    private function sessionsOf(User $user): Builder
    {
        return $this->db->table((string) config('session.table', 'sessions'))->where('user_id', $user->getKey());
    }
}
