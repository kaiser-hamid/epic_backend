<?php

namespace Modules\BloodBank\Data;

use Modules\BloodBank\Models\PatientCase;
use Modules\Shared\Billing\Data\InvoiceSummary;
use Modules\Shared\Patient\Data\PatientSummary;

/**
 * The blood collection screen for one case: patient and invoice (from Shared), the case, and one row per case
 * donor in sequence order. Rendered by BloodCollectionResource.
 */
final readonly class BloodCollectionView
{
    /**
     * @param  list<BloodCollectionRow>  $donors
     * @param  int|null  $collectedCollectionId  the bag drawn by the request that produced this view
     */
    public function __construct(
        public PatientCase $case,
        public ?PatientSummary $patient,
        public ?InvoiceSummary $invoice,
        public array $donors,
        public ?int $collectedCollectionId = null,
    ) {}
}
