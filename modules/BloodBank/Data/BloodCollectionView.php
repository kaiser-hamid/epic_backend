<?php

namespace Modules\BloodBank\Data;

use Modules\BloodBank\Models\PatientCase;
use Modules\Shared\Billing\Data\InvoiceSummary;
use Modules\Shared\Patient\Data\PatientSummary;

/**
 * The blood collection screen for one case: patient and invoice (from Shared), the case (with `phlebotomy`
 * loaded), one row per case donor in sequence order, and the plan for the next bag (empty when the case needs
 * nothing more). A Therapeutic Phlebotomy case has no donors; it is recorded here instead (`canRecordPhlebotomy`).
 * Rendered by BloodCollectionResource.
 */
final readonly class BloodCollectionView
{
    /**
     * @param  list<BloodCollectionRow>  $donors
     * @param  list<PlannedUnit>  $bagPlan  what the next bag is made into
     * @param  int|null  $collectedCollectionId  the bag drawn by the request that produced this view
     * @param  list<UnitView>  $collectedUnits  the units made from that bag, with their label printer commands
     */
    public function __construct(
        public PatientCase $case,
        public ?PatientSummary $patient,
        public ?InvoiceSummary $invoice,
        public array $donors,
        public array $bagPlan = [],
        public ?int $collectedCollectionId = null,
        public array $collectedUnits = [],
        public bool $canRecordPhlebotomy = false,
    ) {}
}
