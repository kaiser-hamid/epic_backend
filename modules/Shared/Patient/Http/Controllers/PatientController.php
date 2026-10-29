<?php

namespace Modules\Shared\Patient\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Shared\Patient\Actions\RegisterPatient;
use Modules\Shared\Patient\Data\RegisterPatientData;
use Modules\Shared\Patient\Http\Resources\PatientResource;
use Modules\Shared\Patient\Models\PatientMaster;
use Modules\Shared\Patient\Queries\ListPatients;
use Modules\Shared\Patient\Queries\LookupPatients;
use Modules\Shared\Support\Data\PhoneLookupData;

final class PatientController
{
    public function __construct(
        private readonly ListPatients $listPatients,
        private readonly LookupPatients $lookupPatients,
        private readonly RegisterPatient $registerPatient,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return PatientResource::collection($this->listPatients->get($request));
    }

    public function lookup(PhoneLookupData $data): AnonymousResourceCollection
    {
        return PatientResource::collection($this->lookupPatients->get($data));
    }

    public function store(Request $request, RegisterPatientData $data): PatientResource
    {
        return PatientResource::make($this->registerPatient->handle($request->user(), $data));
    }

    public function show(PatientMaster $patient): PatientResource
    {
        return PatientResource::make($patient);
    }
}
