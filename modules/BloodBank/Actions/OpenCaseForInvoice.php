<?php

namespace Modules\BloodBank\Actions;

use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Modules\BloodBank\Data\InvoiceCaseDetailsData;
use Modules\BloodBank\Enums\BillableItemKind;
use Modules\BloodBank\Enums\BloodBankAuditAction;
use Modules\BloodBank\Enums\CaseService;
use Modules\BloodBank\Enums\CaseStatus;
use Modules\BloodBank\Exceptions\ServicesNotCombinable;
use Modules\BloodBank\Models\BillableItem;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Services\CaseNumber;
use Modules\Shared\Audit\Contracts\AuditLogger;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Billing\Data\IssuedInvoice;
use Modules\Shared\Billing\Data\IssuedInvoiceLine;

/**
 * Opens the Blood Bank case of a just-saved bill with Blood Bank items, inside the bill's transaction: the patient
 * and branch of the bill, its "Care of", and what the billed items mean to Blood Bank (`bb_billable_items`):
 *  - component items request units (billed quantity × the item's units per quantity, e.g. Platelet (4:1) = 4
 *    platelets); two items of the same component add up;
 *  - a procedure item makes the case that procedure (Autologous PRP, Therapeutic Phlebotomy); otherwise it is a
 *    transfusion case;
 *  - the storage fee keeps the patient's units in storage until a later visit.
 * One bill, one service: a procedure cannot be billed with components or with another procedure
 * (`SERVICES_NOT_COMBINABLE`, which rolls the bill back). Other items (cross matching, bed charges…) can go with
 * any service and request nothing. One bill opens at most one case (`bb_cases.billing_id` is unique); asking
 * again returns the existing case.
 */
final class OpenCaseForInvoice
{
    public function __construct(
        private readonly CaseNumber $caseNumber,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @throws ServicesNotCombinable
     */
    public function handle(User $actor, IssuedInvoice $invoice, InvoiceCaseDetailsData $details): PatientCase
    {
        return DB::transaction(function () use ($actor, $invoice, $details): PatientCase {
            $existing = PatientCase::query()->where('billing_id', $invoice->billingId)->first();

            if ($existing !== null) {
                return $existing;
            }

            $billableItems = $this->billableItems($invoice->lines);
            $components = $this->components($invoice->lines, $billableItems);
            $service = $this->service($billableItems, $components);

            $case = PatientCase::create([
                'case_no' => $this->caseNumber->next(),
                'patient_master_id' => $invoice->patientId,
                'billing_id' => $invoice->billingId,
                'branch_id' => $invoice->branchId,
                'care_type' => $details->careType,
                'service' => $service,
                'keeps_units_in_storage' => $billableItems->contains(fn (BillableItem $item): bool => $item->kind === BillableItemKind::StorageFee),
                'status' => CaseStatus::Open,
                'created_by' => $actor->id,
            ]);

            $case->components()->createMany($components);

            $this->auditLogger->record(BloodBankAuditAction::CaseOpened, $case, newValues: [
                ...$case->only(['case_no', 'patient_master_id', 'billing_id', 'care_type', 'service', 'keeps_units_in_storage']),
                'invoice_no' => $invoice->invoiceNo,
                'components' => $components,
            ], actor: $actor);

            return $case;
        });
    }

    /**
     * The active billable items of the bill's lines, keyed by test item id.
     *
     * @param  list<IssuedInvoiceLine>  $lines
     * @return SupportCollection<int, BillableItem>
     */
    private function billableItems(array $lines): SupportCollection
    {
        return BillableItem::query()
            ->where('is_active', true)
            ->whereIn('test_item_id', array_map(fn (IssuedInvoiceLine $line): int => $line->testItemId, $lines))
            ->get()
            ->keyBy('test_item_id')
            ->toBase();
    }

    /**
     * One requested component per component type, in bill order: the billed quantity times the item's units per
     * quantity, summed over the items billing that type (the first of them is kept as `test_item_id`).
     *
     * @param  list<IssuedInvoiceLine>  $lines
     * @param  SupportCollection<int, BillableItem>  $billableItems
     * @return list<array{component_type_id: int, test_item_id: int, quantity: int}>
     */
    private function components(array $lines, SupportCollection $billableItems): array
    {
        $components = [];

        foreach ($lines as $line) {
            $item = $billableItems->get($line->testItemId);

            if ($item === null || $item->kind !== BillableItemKind::Component || $item->component_type_id === null) {
                continue;
            }

            $quantity = $line->quantity * $item->units_per_quantity;
            $existing = $components[$item->component_type_id] ?? null;

            $components[$item->component_type_id] = [
                'component_type_id' => $item->component_type_id,
                'test_item_id' => $existing['test_item_id'] ?? $line->testItemId,
                'quantity' => ($existing['quantity'] ?? 0) + $quantity,
            ];
        }

        return array_values($components);
    }

    /**
     * @param  SupportCollection<int, BillableItem>  $billableItems
     * @param  list<array{component_type_id: int, test_item_id: int, quantity: int}>  $components
     *
     * @throws ServicesNotCombinable
     */
    private function service(SupportCollection $billableItems, array $components): CaseService
    {
        $procedures = $billableItems
            ->filter(fn (BillableItem $item): bool => $item->kind === BillableItemKind::Procedure && $item->service !== null)
            ->map(fn (BillableItem $item): CaseService => $item->service)
            ->unique(fn (CaseService $service): string => $service->value)
            ->values();

        if ($procedures->count() > 1 || ($procedures->isNotEmpty() && $components !== [])) {
            throw new ServicesNotCombinable;
        }

        return $procedures->first() ?? CaseService::Transfusion;
    }
}
