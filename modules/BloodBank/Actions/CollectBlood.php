<?php

namespace Modules\BloodBank\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\BloodBank\Data\BloodCollectionView;
use Modules\BloodBank\Data\CollectBloodData;
use Modules\BloodBank\Data\CollectedUnitData;
use Modules\BloodBank\Data\PlannedUnit;
use Modules\BloodBank\Enums\BloodBankAuditAction;
use Modules\BloodBank\Enums\CaseDonorStatus;
use Modules\BloodBank\Enums\CaseStatus;
use Modules\BloodBank\Enums\CollectionStatus;
use Modules\BloodBank\Enums\PrintDocumentType;
use Modules\BloodBank\Enums\UnitStatus;
use Modules\BloodBank\Exceptions\BagAlreadyCollected;
use Modules\BloodBank\Exceptions\CaseDonorNotInCase;
use Modules\BloodBank\Exceptions\CaseNeedsNothingMore;
use Modules\BloodBank\Exceptions\CaseNotOpen;
use Modules\BloodBank\Exceptions\DonorDeferred;
use Modules\BloodBank\Exceptions\DonorNotEligible;
use Modules\BloodBank\Exceptions\DonorNotFit;
use Modules\BloodBank\Models\CaseDonor;
use Modules\BloodBank\Models\Collection;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Models\StorageLocation;
use Modules\BloodBank\Models\Unit;
use Modules\BloodBank\Queries\ShowBloodCollection;
use Modules\BloodBank\Services\BagPlan;
use Modules\BloodBank\Services\BloodCollectionGate;
use Modules\BloodBank\Services\UnitMovementRecorder;
use Modules\BloodBank\Services\UnitNumber;
use Modules\Shared\Audit\Contracts\AuditLogger;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Printing\Contracts\PrintLogger;

/**
 * Collects blood from one eligible donor straight into the component bags of its bag plan (see BagPlan; there is
 * no primary bag and no later preparation, whole blood is just another component): patient units are reserved for
 * the case, stock units go to the chosen storage location. The donation is one `bb_collections` row (who, when,
 * notes) without a number of its own; its units share one daily number under their component prefixes
 * (UnitNumber). Each unit carries the donor's blood group, expires its shelf life after the collection and gets
 * its first movement row. The case donor becomes
 * `collected`. Every unit label is logged as printed and its printer command returned with the screen, so the
 * frontend prints them straight away (patient label for patient units, storage label for stock).
 *
 * The case row is locked first, so two donors of one case collected at once cannot both fill the same need.
 */
final class CollectBlood
{
    public function __construct(
        private readonly BloodCollectionGate $collectionGate,
        private readonly BagPlan $bagPlan,
        private readonly UnitNumber $unitNumber,
        private readonly UnitMovementRecorder $movements,
        private readonly PrintLogger $printLogger,
        private readonly AuditLogger $auditLogger,
        private readonly ShowBloodCollection $showBloodCollection,
    ) {}

    /**
     * @throws CaseNotOpen
     * @throws CaseDonorNotInCase
     * @throws DonorNotFit
     * @throws DonorNotEligible
     * @throws DonorDeferred
     * @throws BagAlreadyCollected
     * @throws CaseNeedsNothingMore
     * @throws ValidationException
     */
    public function handle(User $actor, PatientCase $case, CaseDonor $caseDonor, CollectBloodData $data): BloodCollectionView
    {
        $unitPrints = [];

        [$case, $collection] = DB::transaction(function () use ($actor, $case, $caseDonor, $data, &$unitPrints): array {
            $case = PatientCase::query()->lockForUpdate()->findOrFail($case->id);

            if ($case->status !== CaseStatus::Open) {
                throw new CaseNotOpen;
            }

            if ($caseDonor->case_id !== $case->id) {
                throw new CaseDonorNotInCase;
            }

            $caseDonor = CaseDonor::query()->with('donor')->lockForUpdate()->findOrFail($caseDonor->id);

            if (($refusal = $this->collectionGate->refusal($caseDonor)) !== null) {
                throw $refusal;
            }

            $plan = $this->bagPlan->for($case);

            if ($plan === []) {
                throw new CaseNeedsNothingMore;
            }

            $rows = $this->rowsFor($case, $plan, $data);
            $collectedAt = $data->collectedAt ?? now();

            $donationDigits = $this->unitNumber->nextDonation();
            $collection = Collection::query()->create([
                'case_donor_id' => $caseDonor->id,
                'collected_at' => $collectedAt,
                'collected_by' => $actor->id,
                'status' => CollectionStatus::Collected,
                'notes' => $data->notes,
            ]);

            $caseDonor->update(['status' => CaseDonorStatus::Collected]);

            foreach ($plan as $index => $planned) {
                $componentType = $planned->componentType;
                $row = $rows[$index];

                $unit = Unit::query()->create([
                    'branch_id' => $case->branch_id,
                    'unit_no' => $this->unitNumber->forUnit($donationDigits, (string) $componentType->unit_prefix),
                    'collection_id' => $collection->id,
                    'component_type_id' => $componentType->id,
                    'case_id' => $planned->forPatient ? $case->id : null,
                    'blood_group' => $caseDonor->donor->blood_group,
                    'volume_ml' => $row?->volumeMl,
                    'status' => $planned->forPatient ? UnitStatus::Reserved : UnitStatus::Available,
                    'storage_location_id' => $row?->storageLocationId,
                    'expires_at' => $collectedAt->copy()->addHours($componentType->shelf_life_hours),
                ]);

                $this->movements->record($unit, $actor, occurredAt: $collectedAt);
                $unitPrints[$unit->id] = $this->printLogger->record(PrintDocumentType::unitLabelFor($unit->status), $unit, $actor);
            }

            $this->auditLogger->record(BloodBankAuditAction::BloodCollected, $collection, newValues: [
                ...$collection->only(['case_donor_id', 'collected_at', 'notes']),
                'case_no' => $case->case_no,
                'donor_no' => $caseDonor->donor->donor_no,
                'units' => Unit::query()->whereKey(array_keys($unitPrints))->orderBy('id')
                    ->get(['unit_no', 'component_type_id', 'status', 'case_id', 'storage_location_id', 'volume_ml', 'expires_at'])
                    ->toArray(),
            ]);

            return [$case->setRelations([]), $collection];
        });

        return $this->showBloodCollection->get($case, collectedCollectionId: $collection->id, collectedUnitPrints: $unitPrints);
    }

    /**
     * The request row for each planned unit (same index as the plan; null when left out). A row must be a unit of
     * the plan, and a unit that needs a storage location must get an active one of the case's branch.
     *
     * @param  list<PlannedUnit>  $plan
     * @return array<int, CollectedUnitData|null>
     *
     * @throws ValidationException
     */
    private function rowsFor(PatientCase $case, array $plan, CollectBloodData $data): array
    {
        $planIndexes = [];
        foreach ($plan as $index => $planned) {
            $planIndexes[$planned->componentType->id] = $index;
        }

        $locationIds = StorageLocation::query()
            ->whereKey(array_values(array_filter(array_map(fn (CollectedUnitData $row): ?int => $row->storageLocationId, $data->units))))
            ->where('branch_id', $case->branch_id)
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        $rows = array_fill(0, count($plan), null);
        $rowIndexes = [];

        foreach ($data->units as $index => $row) {
            if (! array_key_exists($row->componentTypeId, $planIndexes) || $rows[$planIndexes[$row->componentTypeId]] !== null) {
                throw ValidationException::withMessages(["units.{$index}.component_type_id" => 'This component is not made from this bag.']);
            }

            if ($row->storageLocationId !== null && ! in_array($row->storageLocationId, $locationIds, true)) {
                throw ValidationException::withMessages(["units.{$index}.storage_location_id" => 'The selected storage location is invalid.']);
            }

            $rows[$planIndexes[$row->componentTypeId]] = $row;
            $rowIndexes[$planIndexes[$row->componentTypeId]] = $index;
        }

        foreach ($plan as $index => $planned) {
            if ($planned->needsStorageLocation && $rows[$index]?->storageLocationId === null) {
                $field = array_key_exists($index, $rowIndexes) ? "units.{$rowIndexes[$index]}.storage_location_id" : 'units';

                throw ValidationException::withMessages([$field => "Choose where the {$planned->componentType->code} unit is stored."]);
            }
        }

        return $rows;
    }
}
