<?php

namespace Modules\BloodBank\Data;

use Modules\BloodBank\Models\PatientCase;
use Modules\Shared\Billing\Data\InvoiceSummary;
use Modules\Shared\Patient\Data\PatientSummary;

/**
 * A case with the patient and invoice details read from Shared through its directories (Blood Bank never
 * loads Shared models). Rendered by CaseResource.
 */
final readonly class CaseView
{
    public function __construct(
        public PatientCase $case,
        public ?PatientSummary $patient,
        public ?InvoiceSummary $invoice,
    ) {}
}
