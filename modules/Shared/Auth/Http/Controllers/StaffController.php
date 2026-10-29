<?php

namespace Modules\Shared\Auth\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Shared\Auth\Http\Resources\StaffResource;
use Modules\Shared\Auth\Queries\LookupStaff;
use Modules\Shared\Support\Data\SearchLookupData;

final class StaffController
{
    public function __construct(private readonly LookupStaff $lookupStaff) {}

    public function lookup(SearchLookupData $data): AnonymousResourceCollection
    {
        return StaffResource::collection($this->lookupStaff->get($data));
    }
}
