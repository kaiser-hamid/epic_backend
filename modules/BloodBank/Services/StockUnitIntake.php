<?php

namespace Modules\BloodBank\Services;

use Modules\BloodBank\Enums\BloodBankAuditAction;
use Modules\BloodBank\Enums\CaseDonorSource;
use Modules\BloodBank\Enums\CaseDonorStatus;
use Modules\BloodBank\Exceptions\CaseNotOpen;
use Modules\BloodBank\Exceptions\CaseTakesNoBlood;
use Modules\BloodBank\Exceptions\UnitAlreadyInCase;
use Modules\BloodBank\Exceptions\UnitExpired;
use Modules\BloodBank\Exceptions\UnitNotAvailable;
use Modules\BloodBank\Exceptions\UnitNotFound;
use Modules\BloodBank\Exceptions\UnitNotNeeded;
use Modules\BloodBank\Models\CaseDonor;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Models\Unit;
use Modules\Shared\Audit\Contracts\AuditLogger;
use Modules\Shared\Auth\Models\User;

/**
 * A stock bag scanned on the sample collection screen. `takeable()` is the scan check (no writes); `admit()` runs
 * at Collect, inside its transaction: the unit joins the case as a stand-in donor and is held for it, so no other
 * case can take it. The new case donor row (`source = stock`) points to the unit and its original donor, is billed
 * to the case's bill and is `fit` at once (a stock bag has no physical exam; its donor was examined at donation).
 * The caller then draws the row's sample as for any donor.
 */
final class StockUnitIntake
{
    public function __construct(
        private readonly StockUnitGate $stockUnitGate,
        private readonly StockUnitHold $stockUnitHold,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * The unit of the case's branch with this unit no, when it can be taken for the case now. `$forUpdate` locks
     * the unit row (at Collect).
     *
     * @throws UnitNotFound
     * @throws CaseNotOpen
     * @throws CaseTakesNoBlood
     * @throws UnitAlreadyInCase
     * @throws UnitNotAvailable
     * @throws UnitExpired
     * @throws UnitNotNeeded
     */
    public function takeable(PatientCase $case, string $unitNo, bool $forUpdate = false): Unit
    {
        $unit = Unit::query()
            ->where('branch_id', $case->branch_id)
            ->where('unit_no', trim($unitNo))
            ->when($forUpdate, fn ($query) => $query->lockForUpdate())
            ->first() ?? throw new UnitNotFound;

        if (($refusal = $this->stockUnitGate->takeRefusal($case, $unit)) !== null) {
            throw $refusal;
        }

        return $unit;
    }

    /**
     * Adds the stock row for the unit and holds the unit for the case. The case row must already be locked.
     *
     * @throws UnitNotFound
     * @throws CaseNotOpen
     * @throws CaseTakesNoBlood
     * @throws UnitAlreadyInCase
     * @throws UnitNotAvailable
     * @throws UnitExpired
     * @throws UnitNotNeeded
     */
    public function admit(User $actor, PatientCase $case, string $unitNo): CaseDonor
    {
        $unit = $this->takeable($case, $unitNo, forUpdate: true);
        $unit->load('collection.caseDonor');

        $caseDonor = CaseDonor::query()->create([
            'case_id' => $case->id,
            'donor_id' => $unit->collection->caseDonor->donor_id,
            'source' => CaseDonorSource::Stock,
            'unit_id' => $unit->id,
            'billing_id' => $case->billing_id,
            'sequence_no' => (int) $case->caseDonors()->max('sequence_no') + 1,
            'status' => CaseDonorStatus::Fit,
            'assessed_by' => $actor->id,
            'assessed_at' => now(),
        ]);

        $this->auditLogger->record(BloodBankAuditAction::CaseDonorAdded, $case, newValues: [
            'case_no' => $case->case_no,
            'case_donor_id' => $caseDonor->id,
            'donor_id' => $caseDonor->donor_id,
            'source' => CaseDonorSource::Stock->value,
            'unit_no' => $unit->unit_no,
            'sequence_no' => $caseDonor->sequence_no,
            'status' => CaseDonorStatus::Fit->value,
        ]);

        $this->stockUnitHold->hold($actor, $case, $unit);

        return $caseDonor;
    }
}
