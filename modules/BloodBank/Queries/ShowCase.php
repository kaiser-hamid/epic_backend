<?php

namespace Modules\BloodBank\Queries;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\BloodBank\Data\CaseLookupData;
use Modules\BloodBank\Data\CaseView;
use Modules\BloodBank\Exceptions\CaseNotFound;
use Modules\BloodBank\Models\PatientCase;
use Modules\Shared\Billing\Contracts\InvoiceDirectory;
use Modules\Shared\Branch\Contracts\CurrentBranch;
use Modules\Shared\Patient\Contracts\PatientDirectory;

/**
 * One case with its components, donors in sequence order (each with its own bill, if any), patient and invoice.
 */
final class ShowCase
{
    public function __construct(
        private readonly PatientDirectory $patients,
        private readonly InvoiceDirectory $invoices,
        private readonly CurrentBranch $currentBranch,
    ) {}

    /**
     * The session branch's case billed by this invoice: the case bill or an added donor's bill.
     *
     * @throws CaseNotFound
     */
    public function find(CaseLookupData $data): CaseView
    {
        $billingId = $this->invoices->idForInvoiceNo($data->invoiceNo) ?? throw new CaseNotFound;

        return $this->get(PatientCase::query()
            ->where('branch_id', $this->currentBranch->id())
            ->billedBy($billingId)
            ->latest('id')
            ->first() ?? throw new CaseNotFound);
    }

    public function get(PatientCase $case): CaseView
    {
        $case->load([
            'components.componentType',
            'caseDonors' => fn (HasMany $query) => $query->orderBy('sequence_no'),
            'caseDonors.donor',
        ]);

        $billingIds = $case->caseDonors->pluck('billing_id')->push($case->billing_id)->filter()->unique()->values()->all();
        $invoices = $this->invoices->summaries($billingIds);

        return new CaseView(
            $case,
            $this->patients->summaries([$case->patient_master_id])[$case->patient_master_id] ?? null,
            $case->billing_id === null ? null : ($invoices[$case->billing_id] ?? null),
            $invoices,
        );
    }
}
