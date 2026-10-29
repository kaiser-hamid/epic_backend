<?php

namespace Modules\Shared\Doctor\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Shared\Doctor\Http\Resources\DoctorResource;
use Modules\Shared\Doctor\Queries\LookupDoctors;
use Modules\Shared\Support\Data\SearchLookupData;

final class DoctorController
{
    public function __construct(private readonly LookupDoctors $lookupDoctors) {}

    public function lookup(SearchLookupData $data): AnonymousResourceCollection
    {
        return DoctorResource::collection($this->lookupDoctors->get($data));
    }
}
