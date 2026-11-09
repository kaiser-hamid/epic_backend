<?php

namespace Modules\BloodBank\Actions;

use Illuminate\Support\Facades\DB;
use Modules\BloodBank\Data\InvoiceCaseDetailsData;
use Modules\BloodBank\Enums\BloodBankAuditAction;
use Modules\BloodBank\Enums\CaseStatus;
use Modules\BloodBank\Models\ComponentType;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Services\CaseNumber;
use Modules\Shared\Audit\Contracts\AuditLogger;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Billing\Data\IssuedInvoice;
use Modules\Shared\Billing\Data\IssuedInvoiceLine;

/**
 * Opens the Blood Bank case of a just-saved Blood Bank bill, inside the bill's transaction: the patient and branch
 * of the bill, its "Care of", and one requested component per billed line whose item is a component type's item
 * (quantity as billed). Lines of other Blood Bank items (cross matching, bag charge…) request nothing. One bill
 * opens at most one case (`bb_cases.billing_id` is unique); asking again returns the existing case.
 */
final class OpenCaseForInvoice
{
    public function __construct(
        private readonly CaseNumber $caseNumber,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(User $actor, IssuedInvoice $invoice, InvoiceCaseDetailsData $details): PatientCase
    {
        return DB::transaction(function () use ($actor, $invoice, $details): PatientCase {
            $existing = PatientCase::query()->where('billing_id', $invoice->billingId)->first();

            if ($existing !== null) {
                return $existing;
            }

            $case = PatientCase::create([
                'case_no' => $this->caseNumber->next(),
                'patient_master_id' => $invoice->patientId,
                'billing_id' => $invoice->billingId,
                'branch_id' => $invoice->branchId,
                'care_type' => $details->careType,
                'status' => CaseStatus::Open,
                'created_by' => $actor->id,
            ]);

            $components = $this->components($invoice->lines);
            $case->components()->createMany($components);

            $this->auditLogger->record(BloodBankAuditAction::CaseOpened, $case, newValues: [
                ...$case->only(['case_no', 'patient_master_id', 'billing_id', 'care_type']),
                'invoice_no' => $invoice->invoiceNo,
                'components' => $components,
            ], actor: $actor);

            return $case;
        });
    }

    /**
     * @param  list<IssuedInvoiceLine>  $lines
     * @return list<array{component_type_id: int, test_item_id: int, quantity: int}>
     */
    private function components(array $lines): array
    {
        $componentTypes = ComponentType::query()
            ->where('is_active', true)
            ->whereIn('test_item_id', array_map(fn (IssuedInvoiceLine $line): int => $line->testItemId, $lines))
            ->get()
            ->keyBy('test_item_id');

        $components = [];

        foreach ($lines as $line) {
            $componentType = $componentTypes->get($line->testItemId);

            if ($componentType !== null) {
                $components[] = [
                    'component_type_id' => $componentType->id,
                    'test_item_id' => $line->testItemId,
                    'quantity' => $line->quantity,
                ];
            }
        }

        return $components;
    }
}
