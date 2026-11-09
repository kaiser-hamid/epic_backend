<?php

namespace Modules\Shared\Billing\Http\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Shared\Billing\Http\Resources\PatientTypeResource;
use Modules\Shared\Billing\Queries\ListPatientTypes;

final class PatientTypeController
{
    public function __construct(private readonly ListPatientTypes $listPatientTypes) {}

    public function index(): AnonymousResourceCollection
    {
        return PatientTypeResource::collection($this->listPatientTypes->get());
    }
}
