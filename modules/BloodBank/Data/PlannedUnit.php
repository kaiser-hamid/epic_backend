<?php

namespace Modules\BloodBank\Data;

use Modules\BloodBank\Models\ComponentType;

/**
 * One unit a donor's bag will be made into at collection: its component and whether it goes to the patient of the
 * case (reserved for them) or to stock. `needsStorageLocation` is true for a stock unit, and for a patient unit of
 * a case that keeps its units in storage (SWPF01).
 */
final readonly class PlannedUnit
{
    public function __construct(
        public ComponentType $componentType,
        public bool $forPatient,
        public bool $needsStorageLocation,
    ) {}
}
