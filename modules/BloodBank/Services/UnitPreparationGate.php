<?php

namespace Modules\BloodBank\Services;

use Modules\BloodBank\Enums\CollectionStatus;
use Modules\BloodBank\Enums\UnitStatus;
use Modules\BloodBank\Exceptions\BagAlreadyPrepared;
use Modules\BloodBank\Exceptions\BagCancelled;
use Modules\BloodBank\Models\Collection;
use Modules\BloodBank\Models\PatientCase;
use Modules\Shared\Support\Exceptions\DomainException;

/**
 * Whether units can be prepared from a bag (once per bag, not from a cancelled one), and how many units of each
 * component a case already holds and still needs.
 */
final class UnitPreparationGate
{
    /**
     * The reason the bag cannot be prepared now, or null when it can.
     */
    public function refusal(Collection $collection): ?DomainException
    {
        return match (true) {
            $collection->status === CollectionStatus::Cancelled => new BagCancelled,
            $collection->units()->exists() => new BagAlreadyPrepared,
            default => null,
        };
    }

    /**
     * Units reserved for or issued to the case, per component type id.
     *
     * @return array<int, int>
     */
    public function heldCounts(PatientCase $case): array
    {
        return $case->units()
            ->whereIn('status', [UnitStatus::Reserved, UnitStatus::Issued])
            ->selectRaw('component_type_id, count(*) as aggregate')
            ->groupBy('component_type_id')
            ->pluck('aggregate', 'component_type_id')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }

    /**
     * Units the case was billed for and does not hold yet, per component type id; only types still needed.
     *
     * @return array<int, int>
     */
    public function neededCounts(PatientCase $case): array
    {
        $held = $this->heldCounts($case);

        return $case->components()
            ->pluck('quantity', 'component_type_id')
            ->map(fn (mixed $quantity, int $componentTypeId): int => (int) $quantity - ($held[$componentTypeId] ?? 0))
            ->filter(fn (int $count): bool => $count > 0)
            ->all();
    }
}
