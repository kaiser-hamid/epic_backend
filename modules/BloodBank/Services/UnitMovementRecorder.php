<?php

namespace Modules\BloodBank\Services;

use Carbon\CarbonInterface;
use Modules\BloodBank\Enums\UnitStatus;
use Modules\BloodBank\Models\Unit;
use Modules\BloodBank\Models\UnitMovement;
use Modules\Shared\Auth\Models\User;

/**
 * Appends the history row of a unit change. Call it after updating the unit: the unit's current status,
 * location and case are the "to" side. A unit's history is never edited.
 */
final class UnitMovementRecorder
{
    public function record(
        Unit $unit,
        User $actor,
        ?UnitStatus $fromStatus = null,
        ?int $fromLocationId = null,
        ?string $reason = null,
        ?CarbonInterface $occurredAt = null,
    ): UnitMovement {
        return UnitMovement::query()->create([
            'unit_id' => $unit->id,
            'from_status' => $fromStatus,
            'to_status' => $unit->status,
            'from_location_id' => $fromLocationId,
            'to_location_id' => $unit->storage_location_id,
            'case_id' => $unit->case_id,
            'reason' => $reason,
            'performed_by' => $actor->id,
            'occurred_at' => $occurredAt ?? now(),
        ]);
    }
}
