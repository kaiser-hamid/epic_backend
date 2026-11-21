<?php

namespace Modules\BloodBank\Services;

use Modules\BloodBank\Enums\CaseDonorStatus;
use Modules\BloodBank\Enums\CaseService;
use Modules\BloodBank\Enums\CaseStatus;
use Modules\BloodBank\Enums\UnitStatus;
use Modules\BloodBank\Exceptions\BagAlreadyCollected;
use Modules\BloodBank\Exceptions\CaseDonorNotStockUnit;
use Modules\BloodBank\Exceptions\CaseNotOpen;
use Modules\BloodBank\Exceptions\CaseTakesNoBlood;
use Modules\BloodBank\Exceptions\DonorDeferred;
use Modules\BloodBank\Exceptions\DonorNotEligible;
use Modules\BloodBank\Exceptions\UnitAlreadyInCase;
use Modules\BloodBank\Exceptions\UnitExpired;
use Modules\BloodBank\Exceptions\UnitNotAvailable;
use Modules\BloodBank\Exceptions\UnitNotHeld;
use Modules\BloodBank\Exceptions\UnitNotNeeded;
use Modules\BloodBank\Models\CaseDonor;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Models\Unit;
use Modules\Shared\Support\Exceptions\DomainException;

/**
 * Whether a stock unit can be taken for a case (its bag sample drawn, which holds it), and whether a stock row's
 * patient label can be printed (which reserves the unit). Blood group is not checked: serology and the crossmatch
 * decide, never the system.
 */
final class StockUnitGate
{
    public function __construct(private readonly BagPlan $bagPlan) {}

    /**
     * The reason the unit cannot be taken for the case now, or null when it can: an open transfusion case, a unit
     * of the case's branch not taken for this case before, in stock (`available`), not expired, and of a component
     * the case still needs (billed and not yet held, reserved or issued).
     */
    public function takeRefusal(PatientCase $case, Unit $unit): ?DomainException
    {
        return match (true) {
            $case->status !== CaseStatus::Open => new CaseNotOpen,
            $case->service !== CaseService::Transfusion => new CaseTakesNoBlood,
            $case->caseDonors()->where('unit_id', $unit->id)->exists() => new UnitAlreadyInCase,
            $unit->status !== UnitStatus::Available => new UnitNotAvailable,
            $unit->expires_at->isPast() => new UnitExpired,
            ($this->bagPlan->neededCounts($case)[$unit->component_type_id] ?? 0) < 1 => new UnitNotNeeded,
            default => null,
        };
    }

    /**
     * The reason a held unit cannot be taken back for its stock row (serology switched from Defer to Accept), or
     * null when it can: the unit is back in stock and not expired. The case already counted it, so the need is not
     * checked again.
     */
    public function retakeRefusal(Unit $unit): ?DomainException
    {
        return match (true) {
            $unit->status !== UnitStatus::Available => new UnitNotAvailable,
            $unit->expires_at->isPast() => new UnitExpired,
            default => null,
        };
    }

    /**
     * The reason the stock row's patient label cannot be printed now, or null when it can: the row is a stock
     * unit accepted at serology (`eligible`) whose unit is still held for the case and not expired. The case
     * being open is the caller's check.
     */
    public function reserveRefusal(CaseDonor $caseDonor): ?DomainException
    {
        $unit = $caseDonor->unit;

        return match (true) {
            ! $caseDonor->isStock() || $unit === null => new CaseDonorNotStockUnit,
            $caseDonor->status === CaseDonorStatus::Collected => new BagAlreadyCollected,
            $caseDonor->status === CaseDonorStatus::Deferred => new DonorDeferred,
            $caseDonor->status !== CaseDonorStatus::Eligible => new DonorNotEligible,
            $unit->status !== UnitStatus::Held || $unit->case_id !== $caseDonor->case_id => new UnitNotHeld,
            $unit->expires_at->isPast() => new UnitExpired,
            default => null,
        };
    }
}
