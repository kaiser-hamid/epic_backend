<?php

namespace Modules\BloodBank\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\BloodBank\Actions\RegisterCase;
use Modules\BloodBank\Data\RegisterCaseData;
use Modules\BloodBank\Http\Resources\CaseResource;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Queries\ListCases;
use Modules\BloodBank\Queries\ShowCase;

final class CaseController
{
    public function __construct(
        private readonly ListCases $listCases,
        private readonly ShowCase $showCase,
        private readonly RegisterCase $registerCase,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return CaseResource::collection($this->listCases->get($request));
    }

    public function store(Request $request, RegisterCaseData $data): CaseResource
    {
        return CaseResource::make($this->registerCase->handle($request->user(), $data));
    }

    public function show(PatientCase $case): CaseResource
    {
        return CaseResource::make($this->showCase->get($case));
    }
}
