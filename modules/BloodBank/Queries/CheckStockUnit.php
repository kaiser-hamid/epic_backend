<?php

namespace Modules\BloodBank\Queries;

use Modules\BloodBank\Exceptions\CaseNotOpen;
use Modules\BloodBank\Exceptions\CaseTakesNoBlood;
use Modules\BloodBank\Exceptions\UnitAlreadyInCase;
use Modules\BloodBank\Exceptions\UnitExpired;
use Modules\BloodBank\Exceptions\UnitNotAvailable;
use Modules\BloodBank\Exceptions\UnitNotFound;
use Modules\BloodBank\Exceptions\UnitNotNeeded;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Models\Unit;
use Modules\BloodBank\Services\StockUnitIntake;

/**
 * The scan check of a stock bag on the sample collection screen: whether the unit can be taken for the case, with
 * nothing written. The screen then lists it for Collect, which checks again (another case may take it meanwhile).
 */
final class CheckStockUnit
{
    public function __construct(private readonly StockUnitIntake $stockUnitIntake) {}

    /**
     * @throws UnitNotFound
     * @throws CaseNotOpen
     * @throws CaseTakesNoBlood
     * @throws UnitAlreadyInCase
     * @throws UnitNotAvailable
     * @throws UnitExpired
     * @throws UnitNotNeeded
     */
    public function get(PatientCase $case, string $unitNo): Unit
    {
        return $this->stockUnitIntake->takeable($case, $unitNo)->load(['componentType', 'storageLocation']);
    }
}
