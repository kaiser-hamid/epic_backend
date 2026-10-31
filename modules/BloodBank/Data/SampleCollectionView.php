<?php

namespace Modules\BloodBank\Data;

use Modules\BloodBank\Models\PatientCase;
use Modules\Shared\Billing\Data\InvoiceSummary;
use Modules\Shared\Patient\Data\PatientSummary;

/**
 * The sample collection screen for one case: patient and invoice (from Shared), the case, and one row per
 * subject (the patient first, then donors in sequence order). Rendered by SampleCollectionResource.
 */
final readonly class SampleCollectionView
{
    /**
     * @param  list<CollectionSubject>  $subjects
     * @param  array<int, int>  $labelPrintCounts  keyed by sample id
     * @param  list<int>  $collectedSampleIds  samples drawn by the request that produced this view
     */
    public function __construct(
        public PatientCase $case,
        public ?PatientSummary $patient,
        public ?InvoiceSummary $invoice,
        public array $subjects,
        public array $labelPrintCounts,
        public array $collectedSampleIds = [],
    ) {}
}
