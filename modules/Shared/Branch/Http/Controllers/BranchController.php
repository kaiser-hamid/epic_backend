<?php

namespace Modules\Shared\Branch\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Shared\Branch\Actions\CreateBranch;
use Modules\Shared\Branch\Actions\UpdateBranch;
use Modules\Shared\Branch\Data\BranchData;
use Modules\Shared\Branch\Http\Resources\BranchResource;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Branch\Queries\ListBranches;

final class BranchController
{
    public function __construct(
        private readonly ListBranches $listBranches,
        private readonly CreateBranch $createBranch,
        private readonly UpdateBranch $updateBranch,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return BranchResource::collection($this->listBranches->get());
    }

    public function store(BranchData $data): BranchResource
    {
        return BranchResource::make($this->createBranch->handle($data));
    }

    public function update(Branch $branch, BranchData $data): BranchResource
    {
        return BranchResource::make($this->updateBranch->handle($branch, $data));
    }
}
