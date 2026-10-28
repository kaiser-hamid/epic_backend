<?php

namespace Modules\BloodBank\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\BloodBank\Http\Resources\DonorResource;
use Modules\BloodBank\Queries\ListDonors;

final class DonorController
{
    public function __construct(private readonly ListDonors $listDonors) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return DonorResource::collection($this->listDonors->get($request));
    }
}
