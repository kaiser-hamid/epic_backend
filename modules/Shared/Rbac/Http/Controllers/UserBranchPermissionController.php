<?php

namespace Modules\Shared\Rbac\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Rbac\Actions\SyncUserBranchPermissions;
use Modules\Shared\Rbac\Data\SyncBranchPermissionsData;
use Modules\Shared\Rbac\Http\Resources\BranchPermissionsResource;
use Modules\Shared\Rbac\Queries\ListUserBranchPermissions;

final class UserBranchPermissionController
{
    public function __construct(
        private readonly ListUserBranchPermissions $listUserBranchPermissions,
        private readonly SyncUserBranchPermissions $syncUserBranchPermissions,
    ) {}

    public function index(User $user): AnonymousResourceCollection
    {
        return BranchPermissionsResource::collection($this->listUserBranchPermissions->get($user));
    }

    public function update(Request $request, User $user, Branch $branch, SyncBranchPermissionsData $data): BranchPermissionsResource
    {
        return BranchPermissionsResource::make([
            'branch' => $branch,
            'permissions' => $this->syncUserBranchPermissions->handle($request->user(), $user, $branch, $data),
        ]);
    }
}
