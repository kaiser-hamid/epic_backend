<?php

namespace Modules\Shared\Rbac\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Shared\Rbac\Http\Resources\UserAccessResource;
use Modules\Shared\Rbac\Queries\ListUserAccess;

final class UserAccessController
{
    public function __construct(private readonly ListUserAccess $listUserAccess) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return UserAccessResource::collection($this->listUserAccess->get($request));
    }
}
