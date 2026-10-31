<?php

namespace Modules\BloodBank\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\BloodBank\Http\Resources\SampleResource;
use Modules\BloodBank\Queries\ListSamples;

final class SampleController
{
    public function __construct(private readonly ListSamples $listSamples) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return SampleResource::collection($this->listSamples->get($request));
    }
}
