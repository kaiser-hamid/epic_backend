<?php

namespace Modules\BloodBank\Data;

use Modules\BloodBank\Models\Donor;
use Modules\Shared\Patient\Data\PatientSummary;

/**
 * A donor with their donation history (case donors with case and collections loaded) and the patient of each
 * case, read from Shared through its directory. Rendered by DonorDetailResource.
 */
final readonly class DonorView
{
    /**
     * @param  array<int, PatientSummary>  $patients  keyed by patient id
     */
    public function __construct(
        public Donor $donor,
        public array $patients,
    ) {}
}
