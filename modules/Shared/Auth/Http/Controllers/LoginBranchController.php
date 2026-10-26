<?php

namespace Modules\Shared\Auth\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Shared\Auth\Data\LoginBranchesData;
use Modules\Shared\Auth\Queries\ListLoginBranches;
use Modules\Shared\Branch\Http\Resources\BranchResource;

final class LoginBranchController
{
    public function __construct(private readonly ListLoginBranches $listLoginBranches) {}

    public function __invoke(LoginBranchesData $data): AnonymousResourceCollection
    {
        return BranchResource::collection($this->listLoginBranches->get($data->username));
    }
}
