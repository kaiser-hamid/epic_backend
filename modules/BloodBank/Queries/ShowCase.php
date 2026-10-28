<?php

namespace Modules\BloodBank\Queries;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\BloodBank\Data\CaseView;
use Modules\BloodBank\Models\PatientCase;
use Modules\Shared\Billing\Contracts\InvoiceDirectory;
use Modules\Shared\Patient\Contracts\PatientDirectory;

/**
 * One case with its components, donors in sequence order, patient and invoice.
 */
final class ShowCase
{
    public function __construct(
        private readonly PatientDirectory $patients,
        private readonly InvoiceDirectory $invoices,
    ) {}

    public function get(PatientCase $case): CaseView
    {
        $case->load([
            'components.componentType',
            'caseDonors' => fn (HasMany $query) => $query->orderBy('sequence_no'),
            'caseDonors.donor',
        ]);

        return new CaseView(
            $case,
            $this->patients->summaries([$case->patient_master_id])[$case->patient_master_id] ?? null,
            $case->billing_id === null ? null : ($this->invoices->summaries([$case->billing_id])[$case->billing_id] ?? null),
        );
    }
}
