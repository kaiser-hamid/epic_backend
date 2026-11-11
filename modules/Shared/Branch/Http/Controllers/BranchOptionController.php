<?php

namespace Modules\Shared\Branch\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Shared\Branch\Http\Resources\BranchResource;
use Modules\Shared\Branch\Queries\ListBranchOptions;

final class BranchOptionController
{
    public function __construct(private readonly ListBranchOptions $listBranchOptions) {}

    public function __invoke(): AnonymousResourceCollection
    {
        return BranchResource::collection($this->listBranchOptions->get());
    }
}
