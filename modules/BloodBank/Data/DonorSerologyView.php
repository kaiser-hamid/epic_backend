<?php

namespace Modules\BloodBank\Data;

use Modules\BloodBank\Models\PatientCase;
use Modules\Shared\Billing\Data\InvoiceSummary;
use Modules\Shared\Patient\Data\PatientSummary;

/**
 * The donor serology screen for one case: patient and invoice (from Shared), the case, and one row per case
 * donor in sequence order. Rendered by DonorSerologyResource.
 */
final readonly class DonorSerologyView
{
    /**
     * @param  list<DonorSerologyRow>  $donors
     */
    public function __construct(
        public PatientCase $case,
        public ?PatientSummary $patient,
        public ?InvoiceSummary $invoice,
        public array $donors,
    ) {}
}
