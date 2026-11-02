<?php

namespace Modules\BloodBank\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\BloodBank\Actions\RecordDonorSerology;
use Modules\BloodBank\Data\DonorSerologyLookupData;
use Modules\BloodBank\Data\RecordDonorSerologyData;
use Modules\BloodBank\Http\Resources\CaseResource;
use Modules\BloodBank\Http\Resources\DonorSerologyResource;
use Modules\BloodBank\Models\CaseDonor;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Queries\ListPendingDonorSerology;
use Modules\BloodBank\Queries\ShowDonorSerology;

final class DonorSerologyController
{
    public function __construct(
        private readonly ShowDonorSerology $showDonorSerology,
        private readonly ListPendingDonorSerology $listPendingDonorSerology,
        private readonly RecordDonorSerology $recordDonorSerology,
    ) {}

    public function lookup(DonorSerologyLookupData $data): DonorSerologyResource
    {
        return DonorSerologyResource::make($this->showDonorSerology->find($data));
    }

    public function show(PatientCase $case): DonorSerologyResource
    {
        return DonorSerologyResource::make($this->showDonorSerology->get($case));
    }

    public function pending(Request $request): AnonymousResourceCollection
    {
        return CaseResource::collection($this->listPendingDonorSerology->get($request));
    }

    public function record(Request $request, PatientCase $case, CaseDonor $caseDonor, RecordDonorSerologyData $data): DonorSerologyResource
    {
        return DonorSerologyResource::make($this->recordDonorSerology->handle($request->user(), $case, $caseDonor, $data));
    }
}
