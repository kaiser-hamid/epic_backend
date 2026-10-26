<?php

namespace Modules\Shared\Auth\Queries;

use Illuminate\Support\Collection;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Auth\Services\SessionManager;
use Modules\Shared\Branch\Models\Branch;

final class ListUserSessions
{
    public function __construct(private readonly SessionManager $sessions) {}

    /**
     * @return Collection<int, object> each session row with its `branch` attached
     */
    public function get(User $user): Collection
    {
        $sessions = $this->sessions->forUser($user);
        $branches = Branch::withTrashed()->whereKey($sessions->pluck('branch_id')->filter()->unique()->all())->get()->keyBy('id');

        return $sessions->each(function (object $session) use ($branches): void {
            $session->branch = $session->branch_id === null ? null : $branches->get($session->branch_id);
        });
    }
}
