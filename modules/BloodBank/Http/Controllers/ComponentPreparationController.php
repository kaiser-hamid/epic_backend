<?php

namespace Modules\BloodBank\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\BloodBank\Actions\PrepareUnits;
use Modules\BloodBank\Actions\PrintUnitLabels;
use Modules\BloodBank\Data\ComponentPreparationLookupData;
use Modules\BloodBank\Data\PrepareUnitsData;
use Modules\BloodBank\Data\PrintUnitLabelsData;
use Modules\BloodBank\Http\Resources\BagResource;
use Modules\BloodBank\Http\Resources\ComponentPreparationResource;
use Modules\BloodBank\Http\Resources\UnitResource;
use Modules\BloodBank\Models\Collection;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Queries\ListPendingComponentPreparations;
use Modules\BloodBank\Queries\ListUnits;
use Modules\BloodBank\Queries\ShowComponentPreparation;

final class ComponentPreparationController
{
    public function __construct(
        private readonly ShowComponentPreparation $showComponentPreparation,
        private readonly ListPendingComponentPreparations $listPendingComponentPreparations,
        private readonly ListUnits $listUnits,
        private readonly PrepareUnits $prepareUnits,
        private readonly PrintUnitLabels $printUnitLabels,
    ) {}

    public function lookup(ComponentPreparationLookupData $data): ComponentPreparationResource
    {
        return ComponentPreparationResource::make($this->showComponentPreparation->find($data));
    }

    public function show(PatientCase $case, Collection $collection): ComponentPreparationResource
    {
        return ComponentPreparationResource::make($this->showComponentPreparation->get($case, $collection));
    }

    public function pending(Request $request): AnonymousResourceCollection
    {
        return BagResource::collection($this->listPendingComponentPreparations->get($request));
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return UnitResource::collection($this->listUnits->get($request));
    }

    public function prepare(Request $request, PatientCase $case, Collection $collection, PrepareUnitsData $data): ComponentPreparationResource
    {
        return ComponentPreparationResource::make($this->prepareUnits->handle($request->user(), $case, $collection, $data));
    }

    public function printLabels(Request $request, PatientCase $case, PrintUnitLabelsData $data): AnonymousResourceCollection
    {
        return UnitResource::collection($this->printUnitLabels->handle($request->user(), $case, $data));
    }
}
