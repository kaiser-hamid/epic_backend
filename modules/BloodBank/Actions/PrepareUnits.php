<?php

namespace Modules\BloodBank\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\BloodBank\Data\ComponentPreparationView;
use Modules\BloodBank\Data\PreparedUnitData;
use Modules\BloodBank\Data\PrepareUnitsData;
use Modules\BloodBank\Enums\BloodBankAuditAction;
use Modules\BloodBank\Enums\CaseStatus;
use Modules\BloodBank\Enums\UnitStatus;
use Modules\BloodBank\Exceptions\BagAlreadyPrepared;
use Modules\BloodBank\Exceptions\BagCancelled;
use Modules\BloodBank\Exceptions\CaseNotOpen;
use Modules\BloodBank\Exceptions\CollectionNotInCase;
use Modules\BloodBank\Exceptions\ComponentNotNeeded;
use Modules\BloodBank\Exceptions\ComponentRequirementNotMet;
use Modules\BloodBank\Models\Collection;
use Modules\BloodBank\Models\ComponentType;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Models\StorageLocation;
use Modules\BloodBank\Models\Unit;
use Modules\BloodBank\Queries\ShowComponentPreparation;
use Modules\BloodBank\Services\UnitMovementRecorder;
use Modules\BloodBank\Services\UnitNumberGenerator;
use Modules\BloodBank\Services\UnitPreparationGate;
use Modules\Shared\Audit\Contracts\AuditLogger;
use Modules\Shared\Auth\Models\User;

/**
 * Turns a collected bag into units, once: one whole-blood unit (numbered with the bag no), or the components it
 * is separated into (bag no plus component code, plus a split number when a component is made twice). Each unit
 * carries the donor's blood group and expires the component's shelf life after the collection. What the patient
 * gets must match the components the case was billed for (see ensureMatchesCaseRequirement); those units are
 * reserved for the case and need no storage location, the rest is stock at a location of the branch. Every unit
 * gets its first movement row.
 */
final class PrepareUnits
{
    public function __construct(
        private readonly UnitPreparationGate $preparationGate,
        private readonly UnitNumberGenerator $unitNumbers,
        private readonly UnitMovementRecorder $movements,
        private readonly AuditLogger $auditLogger,
        private readonly ShowComponentPreparation $showComponentPreparation,
    ) {}

    /**
     * @throws CaseNotOpen
     * @throws CollectionNotInCase
     * @throws BagCancelled
     * @throws BagAlreadyPrepared
     * @throws ComponentNotNeeded
     * @throws ComponentRequirementNotMet
     * @throws ValidationException
     */
    public function handle(User $actor, PatientCase $case, Collection $collection, PrepareUnitsData $data): ComponentPreparationView
    {
        $componentTypes = $this->componentTypes($data);
        $preparedUnitIds = [];

        [$case, $collection] = DB::transaction(function () use ($actor, $case, $collection, $data, $componentTypes, &$preparedUnitIds): array {
            $case = PatientCase::query()->lockForUpdate()->findOrFail($case->id);

            if ($case->status !== CaseStatus::Open) {
                throw new CaseNotOpen;
            }

            $collection = Collection::query()->with('caseDonor.donor')->lockForUpdate()->findOrFail($collection->id);

            if ($collection->caseDonor->case_id !== $case->id) {
                throw new CollectionNotInCase;
            }

            if (($refusal = $this->preparationGate->refusal($collection)) !== null) {
                throw $refusal;
            }

            $preparedAt = $data->preparedAt ?? now();
            $this->ensurePreparedAfterCollection($collection, $preparedAt);
            $locations = $this->locations($case, $data);
            $this->ensureMatchesCaseRequirement($case, $data, $componentTypes);

            $unitNumbers = $this->unitNumbers($collection, $data, $componentTypes);

            foreach ($data->units as $index => $row) {
                $componentType = $componentTypes[$row->componentTypeId];

                $unit = Unit::query()->create([
                    'branch_id' => $case->branch_id,
                    'unit_no' => $unitNumbers[$index],
                    'collection_id' => $collection->id,
                    'component_type_id' => $componentType->id,
                    'case_id' => $row->forCase ? $case->id : null,
                    'blood_group' => $collection->caseDonor->donor->blood_group,
                    'volume_ml' => $row->volumeMl,
                    'status' => $row->forCase ? UnitStatus::Reserved : UnitStatus::Available,
                    'storage_location_id' => $row->storageLocationId === null ? null : $locations[$row->storageLocationId]->id,
                    'prepared_at' => $preparedAt,
                    'expires_at' => $collection->collected_at->copy()->addHours($componentType->shelf_life_hours),
                ]);

                $this->movements->record($unit, $actor, occurredAt: $preparedAt);
                $preparedUnitIds[] = $unit->id;
            }

            $this->auditLogger->record(BloodBankAuditAction::UnitsPrepared, $collection, newValues: [
                'bag_no' => $collection->bag_no,
                'case_no' => $case->case_no,
                'prepared_at' => $preparedAt,
                'units' => Unit::query()->whereKey($preparedUnitIds)->orderBy('id')
                    ->get(['unit_no', 'component_type_id', 'status', 'case_id', 'storage_location_id', 'volume_ml', 'expires_at'])
                    ->toArray(),
            ]);

            return [$case->setRelations([]), $collection->setRelations([])];
        });

        return $this->showComponentPreparation->get($case, $collection, $preparedUnitIds);
    }

    /**
     * The requested component types, keyed by id. Whole blood is the bag kept as it is, so it cannot be combined
     * with components.
     *
     * @return array<int, ComponentType>
     *
     * @throws ValidationException
     */
    private function componentTypes(PrepareUnitsData $data): array
    {
        $componentTypes = ComponentType::query()
            ->whereKey(array_map(fn (PreparedUnitData $row): int => $row->componentTypeId, $data->units))
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        foreach ($data->units as $index => $row) {
            if (! $componentTypes->has($row->componentTypeId)) {
                throw ValidationException::withMessages(["units.{$index}.component_type_id" => 'The selected component type is invalid.']);
            }
        }

        $hasWholeBlood = collect($data->units)
            ->contains(fn (PreparedUnitData $row): bool => $componentTypes[$row->componentTypeId]->code === ComponentType::WHOLE_BLOOD);

        if ($hasWholeBlood && count($data->units) > 1) {
            throw ValidationException::withMessages(['units' => 'Whole blood is the bag kept as it is; it cannot be combined with components.']);
        }

        return $componentTypes->all();
    }

    /**
     * @throws ValidationException
     */
    private function ensurePreparedAfterCollection(Collection $collection, CarbonInterface $preparedAt): void
    {
        if ($preparedAt->lt($collection->collected_at)) {
            throw ValidationException::withMessages(['prepared_at' => 'The preparation time cannot be before the blood was collected.']);
        }
    }

    /**
     * The chosen storage locations, keyed by id: active locations of the case's branch. A stock unit must be
     * stored somewhere; a unit for the patient may skip the location.
     *
     * @return array<int, StorageLocation>
     *
     * @throws ValidationException
     */
    private function locations(PatientCase $case, PrepareUnitsData $data): array
    {
        $locations = StorageLocation::query()
            ->whereKey(array_values(array_filter(array_map(fn (PreparedUnitData $row): ?int => $row->storageLocationId, $data->units))))
            ->where('branch_id', $case->branch_id)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        foreach ($data->units as $index => $row) {
            if ($row->storageLocationId === null && ! $row->forCase) {
                throw ValidationException::withMessages(["units.{$index}.storage_location_id" => 'A unit for stock needs a storage location.']);
            }

            if ($row->storageLocationId !== null && ! $locations->has($row->storageLocationId)) {
                throw ValidationException::withMessages(["units.{$index}.storage_location_id" => 'The selected storage location is invalid.']);
            }
        }

        return $locations->all();
    }

    /**
     * The preparation must give the patient exactly what the case was billed for and still needs:
     *  - a unit for the patient only of a component the case still needs, up to the outstanding quantity;
     *  - a case that needs whole blood (and no components) gets the bag kept as whole blood for the patient;
     *  - a case that needs components gets the bag separated, with one unit for the patient of each of them.
     * When the case needs nothing more, everything goes to stock.
     *
     * @param  array<int, ComponentType>  $componentTypes  the requested rows' component types, keyed by id
     *
     * @throws ComponentNotNeeded
     * @throws ComponentRequirementNotMet
     */
    private function ensureMatchesCaseRequirement(PatientCase $case, PrepareUnitsData $data, array $componentTypes): void
    {
        $needed = $this->preparationGate->neededCounts($case);

        $forCase = collect($data->units)
            ->filter(fn (PreparedUnitData $row): bool => $row->forCase)
            ->countBy(fn (PreparedUnitData $row): int => $row->componentTypeId);

        foreach ($forCase as $componentTypeId => $count) {
            if ($count > ($needed[$componentTypeId] ?? 0)) {
                throw new ComponentNotNeeded;
            }
        }

        if ($needed === []) {
            return;
        }

        $neededTypes = ComponentType::query()->whereKey(array_keys($needed))->get();
        $wholeBloodNeeded = $neededTypes->contains(fn (ComponentType $type): bool => $type->code === ComponentType::WHOLE_BLOOD);
        $neededComponents = $neededTypes->reject(fn (ComponentType $type): bool => $type->code === ComponentType::WHOLE_BLOOD);
        $keptAsWholeBlood = $componentTypes[$data->units[0]->componentTypeId]->code === ComponentType::WHOLE_BLOOD;

        if ($keptAsWholeBlood) {
            if (! $wholeBloodNeeded) {
                throw new ComponentRequirementNotMet('The case needs '.$neededComponents->pluck('code')->implode(', ').'; separate the bag and reserve them for the patient.');
            }

            if (! $data->units[0]->forCase) {
                throw new ComponentRequirementNotMet('The case needs whole blood; reserve this bag for the patient.');
            }

            return;
        }

        if ($neededComponents->isEmpty()) {
            throw new ComponentRequirementNotMet('The case needs whole blood; keep the bag as whole blood for the patient.');
        }

        $missing = $neededComponents->filter(fn (ComponentType $type): bool => ($forCase[$type->id] ?? 0) === 0);

        if ($missing->isNotEmpty()) {
            throw new ComponentRequirementNotMet('The case needs '.$missing->pluck('code')->implode(', ').' for the patient; add them with "For this patient".');
        }
    }

    /**
     * Unit numbers in request order; a component made more than once from the bag gets split numbers 1, 2, ….
     *
     * @param  array<int, ComponentType>  $componentTypes
     * @return array<int, string>
     */
    private function unitNumbers(Collection $collection, PrepareUnitsData $data, array $componentTypes): array
    {
        /** @var SupportCollection<int, int> $totals */
        $totals = collect($data->units)->countBy(fn (PreparedUnitData $row): int => $row->componentTypeId);
        $seen = [];
        $numbers = [];

        foreach ($data->units as $index => $row) {
            $code = $componentTypes[$row->componentTypeId]->code;

            if ($code === ComponentType::WHOLE_BLOOD) {
                $numbers[$index] = $this->unitNumbers->forUnit($collection->bag_no);

                continue;
            }

            $seen[$row->componentTypeId] = ($seen[$row->componentTypeId] ?? 0) + 1;
            $splitNo = $totals[$row->componentTypeId] > 1 ? $seen[$row->componentTypeId] : null;
            $numbers[$index] = $this->unitNumbers->forUnit($collection->bag_no, $code, $splitNo);
        }

        return $numbers;
    }
}
