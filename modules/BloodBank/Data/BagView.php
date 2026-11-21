<?php

namespace Modules\BloodBank\Data;

use Modules\BloodBank\Models\Collection;
use Modules\Shared\Patient\Data\PatientSummary;

/**
 * A donation (with `caseDonor.donor`, `caseDonor.patientCase`, `collector` and `units.componentType` loaded) and
 * the case's patient. Rendered by BagResource.
 */
final readonly class BagView
{
    public function __construct(
        public Collection $collection,
        public ?PatientSummary $patient,
    ) {}
}
