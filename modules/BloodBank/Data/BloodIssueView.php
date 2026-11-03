<?php

namespace Modules\BloodBank\Data;

use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Models\Sample;
use Modules\BloodBank\Models\TestResult;
use Modules\Shared\Billing\Data\InvoiceSummary;
use Modules\Shared\Patient\Data\PatientSummary;

/**
 * The blood issue screen: everything about one case (with `components` loaded), its patient and bills, the
 * patient sample and its current blood group, one row per case donor and one per unit reserved for or issued to
 * the case. Rendered by BloodIssueResource.
 */
final readonly class BloodIssueView
{
    /**
     * @param  array<int, InvoiceSummary>  $donorInvoices  keyed by billing id
     * @param  list<BloodIssueDonorRow>  $donors
     * @param  list<BloodIssueUnitRow>  $units
     */
    public function __construct(
        public PatientCase $case,
        public ?PatientSummary $patient,
        public ?InvoiceSummary $invoice,
        public array $donorInvoices,
        public ?Sample $patientSample,
        public ?TestResult $patientBloodGroup,
        public array $donors,
        public array $units,
    ) {}

    public function canIssue(): bool
    {
        return $this->patientSample !== null
            && collect($this->units)->contains(fn (BloodIssueUnitRow $row): bool => $row->canIssue);
    }
}
