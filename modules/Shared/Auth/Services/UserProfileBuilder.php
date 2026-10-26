<?php

namespace Modules\Shared\Auth\Services;

use Modules\Shared\Auth\Data\UserProfile;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Rbac\Contracts\AccessChecker;

final class UserProfileBuilder
{
    public function __construct(private readonly AccessChecker $accessChecker) {}

    public function build(User $user, Branch $branch): UserProfile
    {
        return new UserProfile($user, $branch, $this->accessChecker->permissionsIn($user, $branch->getKey()));
    }
}
