<?php

namespace Modules\Shared\Rbac\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Shared\Rbac\Http\Resources\PermissionResource;
use Modules\Shared\Rbac\Queries\ListPermissionCatalog;

final class PermissionController
{
    public function __construct(private readonly ListPermissionCatalog $listPermissionCatalog) {}

    public function __invoke(): AnonymousResourceCollection
    {
        return PermissionResource::collection($this->listPermissionCatalog->get());
    }
}
