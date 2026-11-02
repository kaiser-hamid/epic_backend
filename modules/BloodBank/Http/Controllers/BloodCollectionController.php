<?php

namespace Modules\BloodBank\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\BloodBank\Actions\CollectBlood;
use Modules\BloodBank\Actions\PrintBagLabels;
use Modules\BloodBank\Data\BloodCollectionLookupData;
use Modules\BloodBank\Data\CollectBloodData;
use Modules\BloodBank\Data\PrintBagLabelsData;
use Modules\BloodBank\Http\Resources\BagResource;
use Modules\BloodBank\Http\Resources\BloodCollectionResource;
use Modules\BloodBank\Http\Resources\CaseResource;
use Modules\BloodBank\Models\CaseDonor;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Queries\ListBloodCollections;
use Modules\BloodBank\Queries\ListPendingBloodCollections;
use Modules\BloodBank\Queries\ShowBloodCollection;

final class BloodCollectionController
{
    public function __construct(
        private readonly ShowBloodCollection $showBloodCollection,
        private readonly ListPendingBloodCollections $listPendingBloodCollections,
        private readonly ListBloodCollections $listBloodCollections,
        private readonly CollectBlood $collectBlood,
        private readonly PrintBagLabels $printBagLabels,
    ) {}

    public function lookup(BloodCollectionLookupData $data): BloodCollectionResource
    {
        return BloodCollectionResource::make($this->showBloodCollection->find($data));
    }

    public function show(PatientCase $case): BloodCollectionResource
    {
        return BloodCollectionResource::make($this->showBloodCollection->get($case));
    }

    public function pending(Request $request): AnonymousResourceCollection
    {
        return CaseResource::collection($this->listPendingBloodCollections->get($request));
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return BagResource::collection($this->listBloodCollections->get($request));
    }

    public function collect(Request $request, PatientCase $case, CaseDonor $caseDonor, CollectBloodData $data): BloodCollectionResource
    {
        return BloodCollectionResource::make($this->collectBlood->handle($request->user(), $case, $caseDonor, $data));
    }

    public function printLabels(Request $request, PatientCase $case, PrintBagLabelsData $data): AnonymousResourceCollection
    {
        return BagResource::collection($this->printBagLabels->handle($request->user(), $case, $data));
    }
}
