<?php

namespace Modules\BloodBank\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\BloodBank\Actions\AddCaseDonor;
use Modules\BloodBank\Actions\UpdateCaseDetails;
use Modules\BloodBank\Data\AddCaseDonorData;
use Modules\BloodBank\Data\CaseLookupData;
use Modules\BloodBank\Data\UpdateCaseDetailsData;
use Modules\BloodBank\Http\Resources\CaseResource;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Queries\ListCases;
use Modules\BloodBank\Queries\ShowCase;

final class CaseController
{
    public function __construct(
        private readonly ListCases $listCases,
        private readonly ShowCase $showCase,
        private readonly UpdateCaseDetails $updateCaseDetails,
        private readonly AddCaseDonor $addCaseDonor,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return CaseResource::collection($this->listCases->get($request));
    }

    public function show(PatientCase $case): CaseResource
    {
        return CaseResource::make($this->showCase->get($case));
    }

    public function update(PatientCase $case, UpdateCaseDetailsData $data): CaseResource
    {
        return CaseResource::make($this->updateCaseDetails->handle($case, $data));
    }

    public function lookup(CaseLookupData $data): CaseResource
    {
        return CaseResource::make($this->showCase->find($data));
    }

    public function addDonor(Request $request, PatientCase $case, AddCaseDonorData $data): JsonResponse
    {
        return CaseResource::make($this->addCaseDonor->handle($request->user(), $case, $data))->response()->setStatusCode(201);
    }
}
