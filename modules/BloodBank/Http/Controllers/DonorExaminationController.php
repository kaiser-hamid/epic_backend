<?php

namespace Modules\BloodBank\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\BloodBank\Actions\RecordPhysicalExamination;
use Modules\BloodBank\Data\DonorExaminationLookupData;
use Modules\BloodBank\Data\RecordPhysicalExaminationData;
use Modules\BloodBank\Http\Resources\CaseResource;
use Modules\BloodBank\Http\Resources\DonorExaminationResource;
use Modules\BloodBank\Models\CaseDonor;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Queries\ListPendingDonorExaminations;
use Modules\BloodBank\Queries\ShowDonorExamination;

final class DonorExaminationController
{
    public function __construct(
        private readonly ShowDonorExamination $showDonorExamination,
        private readonly ListPendingDonorExaminations $listPendingDonorExaminations,
        private readonly RecordPhysicalExamination $recordPhysicalExamination,
    ) {}

    public function lookup(DonorExaminationLookupData $data): DonorExaminationResource
    {
        return DonorExaminationResource::make($this->showDonorExamination->find($data));
    }

    public function show(PatientCase $case): DonorExaminationResource
    {
        return DonorExaminationResource::make($this->showDonorExamination->get($case));
    }

    public function pending(Request $request): AnonymousResourceCollection
    {
        return CaseResource::collection($this->listPendingDonorExaminations->get($request));
    }

    public function record(Request $request, PatientCase $case, CaseDonor $caseDonor, RecordPhysicalExaminationData $data): DonorExaminationResource
    {
        return DonorExaminationResource::make($this->recordPhysicalExamination->handle($request->user(), $case, $caseDonor, $data));
    }
}
