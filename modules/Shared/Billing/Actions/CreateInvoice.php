<?php

namespace Modules\Shared\Billing\Actions;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Shared\Audit\Contracts\AuditLogger;
use Modules\Shared\Audit\Enums\AuditAction;
use Modules\Shared\Auth\Exceptions\BranchInactive;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Billing\Contracts\InvoiceCategoryHandler;
use Modules\Shared\Billing\Data\CreateInvoiceData;
use Modules\Shared\Billing\Data\IssuedInvoice;
use Modules\Shared\Billing\Data\IssuedInvoiceLine;
use Modules\Shared\Billing\Data\PricedInvoice;
use Modules\Shared\Billing\Data\PricedLine;
use Modules\Shared\Billing\Enums\BillingStatus;
use Modules\Shared\Billing\Exceptions\DiscountPolicyNotAllowed;
use Modules\Shared\Billing\Exceptions\IdempotencyKeyReused;
use Modules\Shared\Billing\Exceptions\InvoiceCategoryMixed;
use Modules\Shared\Billing\Exceptions\InvoiceNoTaken;
use Modules\Shared\Billing\Exceptions\PaidAmountExceedsPayable;
use Modules\Shared\Billing\Models\Billing;
use Modules\Shared\Billing\Queries\ShowInvoice;
use Modules\Shared\Billing\Services\InvoiceNumber;
use Modules\Shared\Billing\Services\InvoicePricer;
use Modules\Shared\Branch\Contracts\CurrentBranch;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Patient\Actions\RegisterPatient;
use Modules\Shared\Patient\Models\PatientMaster;
use Modules\Shared\TestCatalog\Enums\TestItemCategory;
use Spatie\LaravelData\Data;

/**
 * Bills tests to a patient in the session's branch, registering the patient first when they are new.
 *
 * - Every item must share one category; a Blood Bank bill never carries other items, and the reverse.
 * - Each line snapshots the catalog rate and the discount it got, and the bill snapshots its discount policy,
 *   so later catalog or policy changes never alter an issued bill.
 * - When the category belongs to a module (Blood Bank), that module's handler validates its own fields first and
 *   opens its record from the saved bill in the same transaction: the bill and the record exist together or not
 *   at all.
 * - Resubmitting the same `idempotency_key` returns the bill already saved, so a double click or a retry never
 *   creates a second bill (or a second case).
 */
final class CreateInvoice
{
    /**
     * @param  iterable<InvoiceCategoryHandler>  $categoryHandlers
     */
    public function __construct(
        private readonly RegisterPatient $registerPatient,
        private readonly InvoiceNumber $invoiceNumber,
        private readonly InvoicePricer $pricer,
        private readonly CurrentBranch $currentBranch,
        private readonly AuditLogger $auditLogger,
        private readonly ShowInvoice $showInvoice,
        private readonly iterable $categoryHandlers,
    ) {}

    /**
     * @throws BranchInactive
     * @throws IdempotencyKeyReused
     * @throws InvoiceCategoryMixed
     * @throws DiscountPolicyNotAllowed
     * @throws InvoiceNoTaken
     * @throws PaidAmountExceedsPayable
     * @throws ValidationException
     */
    public function handle(User $actor, CreateInvoiceData $data): Billing
    {
        $branch = $this->currentBranch->get() ?? throw new BranchInactive;

        $saved = $this->savedUnderKey($actor, $data->idempotencyKey);

        if ($saved !== null) {
            return $saved;
        }

        $priced = $this->pricer->price($data->items, $data->patientTypeId, $data->discountPolicyId);

        if ($data->paidAmount > $priced->totals->amount) {
            throw new PaidAmountExceedsPayable;
        }

        $handler = $this->handlerFor($priced->category);
        $details = $this->categoryDetails($handler, $data->categoryDetails);

        try {
            $billing = DB::transaction(fn (): Billing => $this->save($actor, $data, $branch, $priced, $handler, $details));
        } catch (UniqueConstraintViolationException $exception) {
            return $this->savedUnderKey($actor, $data->idempotencyKey) ?? throw $exception;
        }

        return $this->showInvoice->get($billing);
    }

    /**
     * @throws InvoiceNoTaken
     */
    private function save(
        User $actor,
        CreateInvoiceData $data,
        Branch $branch,
        PricedInvoice $priced,
        ?InvoiceCategoryHandler $handler,
        ?Data $details,
    ): Billing {
        if ($data->invoiceNo !== null && Billing::withTrashed()->where('invoice_no', $data->invoiceNo)->exists()) {
            throw new InvoiceNoTaken;
        }

        $patient = $data->patient === null
            ? PatientMaster::query()->findOrFail($data->patientId)
            : $this->registerPatient->handle($actor, $data->patient);

        $totals = $priced->totals;

        $billing = Billing::create([
            'invoice_no' => $data->invoiceNo ?? $this->invoiceNumber->next($branch),
            'idempotency_key' => $data->idempotencyKey,
            'patient_master_id' => $patient->id,
            'branch_id' => $branch->id,
            'category' => $priced->category,
            'patient_type_id' => $priced->patientType->id,
            'subtotal_amount' => $totals->subtotalAmount,
            'discount_amount' => $totals->discountAmount,
            'rounding_amount' => $totals->roundingAmount,
            'amount' => $totals->amount,
            'paid_amount' => $data->paidAmount,
            'status' => BillingStatus::Active,
            'billed_at' => now(),
            'delivery_at' => $data->deliveryAt,
            'created_by' => $actor->id,
        ]);

        $lines = array_map(fn (PricedLine $line): array => [
            'test_item_id' => $line->testItemId,
            'category' => $priced->category,
            'quantity' => $line->quantity,
            'rate' => $line->rate,
            'amount' => $line->amount,
            'discount_rate_bp' => $line->discountRateBp,
            'discount_amount' => $line->discountAmount,
            'net_amount' => $line->netAmount,
        ], $totals->lines);

        $billing->items()->createMany($lines);
        $billing->referral()->create($data->referral->toArray());

        if ($priced->discountPolicy !== null) {
            $billing->discounts()->create([
                'discount_policy_id' => $priced->discountPolicy->id,
                'name' => $priced->discountPolicy->name,
                'type' => $priced->discountPolicy->type,
                'rate_bp' => $priced->discountPolicy->rate_bp,
                'amount' => $totals->discountAmount,
            ]);
        }

        $this->auditLogger->record(AuditAction::InvoiceCreated, $billing, newValues: [
            ...$billing->only(['invoice_no', 'patient_master_id', 'category', 'patient_type_id', 'subtotal_amount', 'discount_amount', 'rounding_amount', 'amount', 'paid_amount', 'delivery_at']),
            'discount_policy' => $priced->discountPolicy?->only(['id', 'name', 'rate_bp']),
            'items' => $lines,
            'referral' => $data->referral->toArray(),
        ]);

        if ($handler !== null && $details !== null) {
            $handler->issued($actor, $this->issued($billing, $patient, $totals->lines), $details);
        }

        return $billing;
    }

    /**
     * The bill this user already saved under the key, or null when the key is new.
     *
     * @throws IdempotencyKeyReused
     */
    private function savedUnderKey(User $actor, string $idempotencyKey): ?Billing
    {
        $billing = Billing::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($billing !== null && $billing->created_by !== $actor->id) {
            throw new IdempotencyKeyReused;
        }

        return $billing === null ? null : $this->showInvoice->get($billing);
    }

    private function handlerFor(TestItemCategory $category): ?InvoiceCategoryHandler
    {
        foreach ($this->categoryHandlers as $handler) {
            if ($handler->category() === $category) {
                return $handler;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $details
     *
     * @throws ValidationException
     */
    private function categoryDetails(?InvoiceCategoryHandler $handler, ?array $details): ?Data
    {
        if ($handler === null) {
            if ($details !== null) {
                throw ValidationException::withMessages(['category_details' => 'This bill takes no category details.']);
            }

            return null;
        }

        try {
            return $handler->validateDetails($details);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())
                ->mapWithKeys(fn (array $messages, string $key): array => ["category_details.{$key}" => $messages])
                ->all());
        }
    }

    /**
     * @param  list<PricedLine>  $lines
     */
    private function issued(Billing $billing, PatientMaster $patient, array $lines): IssuedInvoice
    {
        return new IssuedInvoice(
            billingId: $billing->id,
            invoiceNo: $billing->invoice_no,
            branchId: $billing->branch_id,
            patientId: $patient->id,
            patientNo: $patient->patient_no,
            amount: $billing->amount,
            billedAt: $billing->billed_at->toImmutable(),
            lines: array_map(fn (PricedLine $line): IssuedInvoiceLine => new IssuedInvoiceLine($line->testItemId, $line->quantity), $lines),
        );
    }
}
