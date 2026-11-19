<?php

namespace Modules\BloodBank\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\BloodBank\Actions\PrintUnitLabels;
use Modules\BloodBank\Data\PrintUnitLabelsData;
use Modules\BloodBank\Http\Resources\UnitResource;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Queries\ListUnits;

final class UnitController
{
    public function __construct(
        private readonly ListUnits $listUnits,
        private readonly PrintUnitLabels $printUnitLabels,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return UnitResource::collection($this->listUnits->get($request));
    }

    public function printLabels(Request $request, PatientCase $case, PrintUnitLabelsData $data): AnonymousResourceCollection
    {
        return UnitResource::collection($this->printUnitLabels->handle($request->user(), $case, $data));
    }
}
