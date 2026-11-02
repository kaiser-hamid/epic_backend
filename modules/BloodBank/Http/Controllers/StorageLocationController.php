<?php

namespace Modules\BloodBank\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\BloodBank\Http\Resources\StorageLocationResource;
use Modules\BloodBank\Queries\ListStorageLocations;

final class StorageLocationController
{
    public function __construct(private readonly ListStorageLocations $listStorageLocations) {}

    public function index(): AnonymousResourceCollection
    {
        return StorageLocationResource::collection($this->listStorageLocations->get());
    }
}
