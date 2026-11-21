<?php

namespace Modules\BloodBank\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\BloodBank\Data\BloodCollectionView;
use Modules\BloodBank\Data\ReserveStockUnitData;
use Modules\BloodBank\Enums\CaseDonorStatus;
use Modules\BloodBank\Enums\CaseStatus;
use Modules\BloodBank\Enums\PrintDocumentType;
use Modules\BloodBank\Exceptions\BagAlreadyCollected;
use Modules\BloodBank\Exceptions\CaseDonorNotInCase;
use Modules\BloodBank\Exceptions\CaseDonorNotStockUnit;
use Modules\BloodBank\Exceptions\CaseNotOpen;
use Modules\BloodBank\Exceptions\DonorDeferred;
use Modules\BloodBank\Exceptions\DonorNotEligible;
use Modules\BloodBank\Exceptions\UnitExpired;
use Modules\BloodBank\Exceptions\UnitNotHeld;
use Modules\BloodBank\Models\CaseDonor;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Models\StorageLocation;
use Modules\BloodBank\Models\Unit;
use Modules\BloodBank\Queries\ShowBloodCollection;
use Modules\BloodBank\Services\StockUnitGate;
use Modules\BloodBank\Services\StockUnitHold;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Printing\Contracts\PrintLogger;

/**
 * "Print patient label" for a stock row on the blood collection screen: the stock counterpart of collecting a
 * donor's bag. The held unit becomes `reserved` for the case, the row becomes `collected` (serology is locked from
 * here on), and the unit's patient label (printed over its old storage label) is logged as its first print and
 * returned with the screen. Blood Issue then crossmatches and issues it as any other reserved unit.
 */
final class ReserveStockUnit
{
    public function __construct(
        private readonly StockUnitGate $stockUnitGate,
        private readonly StockUnitHold $stockUnitHold,
        private readonly PrintLogger $printLogger,
        private readonly ShowBloodCollection $showBloodCollection,
    ) {}

    /**
     * @throws CaseNotOpen
     * @throws CaseDonorNotInCase
     * @throws CaseDonorNotStockUnit
     * @throws BagAlreadyCollected
     * @throws DonorDeferred
     * @throws DonorNotEligible
     * @throws UnitNotHeld
     * @throws UnitExpired
     * @throws ValidationException
     */
    public function handle(User $actor, PatientCase $case, CaseDonor $caseDonor, ReserveStockUnitData $data): BloodCollectionView
    {
        [$case, $unitPrints] = DB::transaction(function () use ($actor, $case, $caseDonor, $data): array {
            $case = PatientCase::query()->lockForUpdate()->findOrFail($case->id);

            if ($case->status !== CaseStatus::Open) {
                throw new CaseNotOpen;
            }

            if ($caseDonor->case_id !== $case->id) {
                throw new CaseDonorNotInCase;
            }

            $caseDonor = CaseDonor::query()->lockForUpdate()->findOrFail($caseDonor->id);
            $caseDonor->setRelation('unit', $caseDonor->unit_id === null ? null : Unit::query()->lockForUpdate()->find($caseDonor->unit_id));

            if (($refusal = $this->stockUnitGate->reserveRefusal($caseDonor)) !== null) {
                throw $refusal;
            }

            /** @var Unit $unit */
            $unit = $caseDonor->unit;

            $this->stockUnitHold->reserve($actor, $case, $unit, $this->storageLocationId($case, $data));
            $caseDonor->update(['status' => CaseDonorStatus::Collected]);

            return [$case->setRelations([]), [$unit->id => $this->printLogger->record(PrintDocumentType::PatientLabel, $unit, $actor)]];
        });

        return $this->showBloodCollection->get($case, collectedUnitPrints: $unitPrints);
    }

    /**
     * @throws ValidationException
     */
    private function storageLocationId(PatientCase $case, ReserveStockUnitData $data): ?int
    {
        if ($data->storageLocationId === null) {
            return null;
        }

        $isValid = StorageLocation::query()
            ->whereKey($data->storageLocationId)
            ->where('branch_id', $case->branch_id)
            ->where('is_active', true)
            ->exists();

        if (! $isValid) {
            throw ValidationException::withMessages(['storage_location_id' => 'The selected storage location is invalid.']);
        }

        return $data->storageLocationId;
    }
}
