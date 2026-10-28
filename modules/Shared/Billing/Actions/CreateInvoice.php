<?php

namespace Modules\Shared\Billing\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Shared\Audit\Contracts\AuditLogger;
use Modules\Shared\Audit\Enums\AuditAction;
use Modules\Shared\Auth\Exceptions\BranchInactive;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Billing\Data\CreateInvoiceData;
use Modules\Shared\Billing\Data\InvoiceItemData;
use Modules\Shared\Billing\Enums\BillingStatus;
use Modules\Shared\Billing\Models\Billing;
use Modules\Shared\Billing\Services\InvoiceNumber;
use Modules\Shared\Branch\Contracts\CurrentBranch;
use Modules\Shared\Patient\Actions\RegisterPatient;
use Modules\Shared\Patient\Models\PatientMaster;
use Modules\Shared\TestCatalog\Models\TestItem;

/**
 * Bills tests to a patient in the session's branch, registering the patient first when they are new.
 * Each line snapshots the catalog rate, so later rate changes never alter an issued bill.
 */
final class CreateInvoice
{
    public function __construct(
        private readonly RegisterPatient $registerPatient,
        private readonly InvoiceNumber $invoiceNumber,
        private readonly CurrentBranch $currentBranch,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @throws BranchInactive
     */
    public function handle(User $actor, CreateInvoiceData $data): Billing
    {
        $branch = $this->currentBranch->get() ?? throw new BranchInactive;

        return DB::transaction(function () use ($actor, $data, $branch): Billing {
            $patient = $data->patient === null
                ? PatientMaster::query()->findOrFail($data->patientId)
                : $this->registerPatient->handle($actor, $data->patient);

            $lines = $this->lines($data->items);

            $billing = Billing::create([
                'invoice_no' => $this->invoiceNumber->next($branch),
                'patient_master_id' => $patient->id,
                'branch_id' => $branch->id,
                'amount' => array_sum(array_column($lines, 'amount')),
                'status' => BillingStatus::Active,
                'billed_at' => now(),
                'created_by' => $actor->id,
            ]);

            $billing->items()->createMany($lines);
            $billing->referral()->create($data->referral->toArray());

            $this->auditLogger->record(AuditAction::InvoiceCreated, $billing, newValues: [
                ...$billing->only(['invoice_no', 'patient_master_id', 'amount']),
                'items' => $lines,
                'referral' => $data->referral->toArray(),
            ]);

            return $billing->load(['patient', 'items.testItem', 'referral.doctor', 'referral.secondDoctor', 'referral.referredByStaff']);
        });
    }

    /**
     * @param  list<InvoiceItemData>  $items
     * @return list<array{test_item_id: int, quantity: int, rate: int, amount: int}>
     */
    private function lines(array $items): array
    {
        $testItems = TestItem::query()->findMany(array_column($items, 'testItemId'))->keyBy('id');

        return array_map(fn (InvoiceItemData $item): array => [
            'test_item_id' => $item->testItemId,
            'quantity' => $item->quantity,
            'rate' => $testItems[$item->testItemId]->rate,
            'amount' => $testItems[$item->testItemId]->rate * $item->quantity,
        ], $items);
    }
}
