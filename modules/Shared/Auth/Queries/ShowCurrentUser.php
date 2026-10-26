<?php

namespace Modules\Shared\Auth\Queries;

use Modules\Shared\Auth\Data\UserProfile;
use Modules\Shared\Auth\Exceptions\BranchInactive;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Auth\Services\UserProfileBuilder;
use Modules\Shared\Branch\Contracts\CurrentBranch;

final class ShowCurrentUser
{
    public function __construct(
        private readonly CurrentBranch $currentBranch,
        private readonly UserProfileBuilder $profileBuilder,
    ) {}

    /**
     * @throws BranchInactive
     */
    public function get(User $user): UserProfile
    {
        $branch = $this->currentBranch->get() ?? throw new BranchInactive;

        return $this->profileBuilder->build($user, $branch);
    }
}
