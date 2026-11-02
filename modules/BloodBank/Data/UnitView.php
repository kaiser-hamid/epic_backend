<?php

namespace Modules\BloodBank\Data;

use Modules\BloodBank\Models\Unit;
use Modules\Shared\Patient\Data\PatientSummary;
use Modules\Shared\Printing\Models\PrintLog;

/**
 * A unit (with `componentType`, `storageLocation`, `collection.caseDonor.donor` and `patientCase` loaded) and the
 * patient of the case it is reserved for or issued to. On a label print, `screening` holds the donor's screening
 * results and `labelPrint` the print just recorded. Rendered by UnitResource.
 */
final readonly class UnitView
{
    /**
     * @param  list<array{code: string, name: string, result: string|null}>|null  $screening
     */
    public function __construct(
        public Unit $unit,
        public ?PatientSummary $patient,
        public ?array $screening = null,
        public ?PrintLog $labelPrint = null,
    ) {}
}
