<?php

namespace Modules\Shared\Auth\Data;

use Modules\Shared\Auth\Models\User;
use Modules\Shared\Branch\Models\Branch;

/**
 * The signed-in user in their session branch, with the permissions they hold there.
 */
final readonly class UserProfile
{
    /**
     * @param  list<string>  $permissions
     */
    public function __construct(
        public User $user,
        public Branch $branch,
        public array $permissions,
    ) {}
}
