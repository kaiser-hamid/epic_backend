<?php

namespace Modules\BloodBank\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\BloodBank\Actions\CollectSamples;
use Modules\BloodBank\Actions\PrintSampleLabels;
use Modules\BloodBank\Data\CheckStockUnitData;
use Modules\BloodBank\Data\CollectSamplesData;
use Modules\BloodBank\Data\PrintSampleLabelsData;
use Modules\BloodBank\Data\SampleCollectionLookupData;
use Modules\BloodBank\Http\Resources\CaseResource;
use Modules\BloodBank\Http\Resources\SampleCollectionResource;
use Modules\BloodBank\Http\Resources\SampleResource;
use Modules\BloodBank\Http\Resources\StockUnitResource;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Queries\CheckStockUnit;
use Modules\BloodBank\Queries\ListPendingSampleCollections;
use Modules\BloodBank\Queries\ShowSampleCollection;

final class SampleCollectionController
{
    public function __construct(
        private readonly ShowSampleCollection $showSampleCollection,
        private readonly ListPendingSampleCollections $listPendingSampleCollections,
        private readonly CollectSamples $collectSamples,
        private readonly PrintSampleLabels $printSampleLabels,
        private readonly CheckStockUnit $checkStockUnit,
    ) {}

    public function lookup(SampleCollectionLookupData $data): SampleCollectionResource
    {
        return SampleCollectionResource::make($this->showSampleCollection->find($data));
    }

    public function show(PatientCase $case): SampleCollectionResource
    {
        return SampleCollectionResource::make($this->showSampleCollection->get($case));
    }

    public function pending(Request $request): AnonymousResourceCollection
    {
        return CaseResource::collection($this->listPendingSampleCollections->get($request));
    }

    public function collect(Request $request, PatientCase $case, CollectSamplesData $data): SampleCollectionResource
    {
        return SampleCollectionResource::make($this->collectSamples->handle($request->user(), $case, $data));
    }

    public function checkStockUnit(PatientCase $case, CheckStockUnitData $data): StockUnitResource
    {
        return StockUnitResource::make($this->checkStockUnit->get($case, $data->unitNo));
    }

    public function printLabels(Request $request, PatientCase $case, PrintSampleLabelsData $data): AnonymousResourceCollection
    {
        return SampleResource::collection($this->printSampleLabels->handle($request->user(), $case, $data));
    }
}
