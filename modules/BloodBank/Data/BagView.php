<?php

namespace Modules\BloodBank\Data;

use Modules\BloodBank\Models\Collection;
use Modules\Shared\Patient\Data\PatientSummary;
use Modules\Shared\Printing\Models\PrintLog;

/**
 * A blood bag (with `caseDonor.donor`, `caseDonor.patientCase` and `collector` loaded) and the case's patient;
 * also the content of a bag label. `labelPrint` is set when the label was just printed. Rendered by BagResource.
 */
final readonly class BagView
{
    public function __construct(
        public Collection $collection,
        public ?PatientSummary $patient,
        public ?PrintLog $labelPrint = null,
    ) {}
}
