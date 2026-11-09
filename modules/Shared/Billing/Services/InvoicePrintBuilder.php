<?php

namespace Modules\Shared\Billing\Services;

use Illuminate\Support\Collection;
use Modules\Shared\Billing\Models\Billing;
use Modules\Shared\Billing\Models\BillingItem;
use Modules\Shared\Billing\Queries\ShowInvoice;
use Modules\Shared\Doctor\Models\Doctor;
use Modules\Shared\Printing\Contracts\PdfRenderer;
use Modules\Shared\Printing\Data\PdfDocument;
use Modules\Shared\Printing\Data\PdfPageSetup;

/**
 * Builds the bill PDF printed at the counter, laid out like the paper bill and printed on the pre-printed pad
 * (A5, 148 × 210 mm; the top and bottom 27 mm hold the pad's own header and QR footer, so nothing is drawn there).
 *
 * One print is the Customer Copy (every item with its price, the totals, the payment and the amount in words)
 * followed by one Non Lab Copy per department on the bill: the same header, that department's items without
 * prices, and the department's name and room. Recording the print is the caller's job.
 */
final class InvoicePrintBuilder
{
    private const string BILL_DATE = 'd/m/y h:i A';

    private const string DELIVERY_DATE = 'd/m/y h:i:s A';

    private const string PRINTED_AT = 'd/m/Y h:i:s A';

    public function __construct(
        private readonly PdfRenderer $pdfRenderer,
        private readonly ShowInvoice $showInvoice,
        private readonly TakaInWords $takaInWords,
    ) {}

    /**
     * @param  bool  $isDuplicate  printed before; the header says "Duplicate Copy" instead of "Original Copy"
     * @param  bool  $isPreview  not a print: the header says "Preview" and nothing is logged
     */
    public function build(Billing $billing, bool $isDuplicate = false, bool $isPreview = false): PdfDocument
    {
        $billing = $this->showInvoice->get($billing)->loadMissing('items.testItem.department');

        $content = $this->pdfRenderer->render('billing::invoice', [
            'header' => $this->header($billing, $isDuplicate, $isPreview),
            'customerCopy' => $this->customerCopy($billing),
            'departmentCopies' => $this->departmentCopies($billing),
        ], new PdfPageSetup(
            width: 148,
            height: 210,
            marginTop: 27,
            marginBottom: 27,
            marginLeft: 10,
            marginRight: 10,
        ));

        return new PdfDocument($content, "INV-{$billing->invoice_no}.pdf");
    }

    /**
     * What every copy repeats at the top and bottom.
     *
     * @return array<string, mixed>
     */
    private function header(Billing $billing, bool $isDuplicate, bool $isPreview): array
    {
        $patient = $billing->patient;
        $referral = $billing->referral;

        return [
            'printed_at' => now()->format(self::PRINTED_AT),
            'billed_at' => $billing->billed_at->format(self::BILL_DATE),
            'invoice_no' => $billing->invoice_no,
            'patient_no' => $patient->patient_no,
            'copy_status' => match (true) {
                $isPreview => 'Preview',
                $isDuplicate => 'Duplicate Copy',
                default => 'Original Copy',
            },
            'name' => mb_strtoupper(trim(($patient->title?->label() ?? '').' '.$patient->name)),
            'age' => $patient->ageLabel(),
            'gender' => $patient->gender?->label(),
            'phone' => $patient->phone,
            'referred_by' => $referral === null ? null : ($referral->is_self_referral ? 'Self' : $this->doctor($referral->doctor)),
            'staff_code' => $referral?->referredByStaff?->employee_id,
            'remarks' => $referral?->remark,
            'delivery_at' => $billing->delivery_at?->format(self::DELIVERY_DATE),
            'is_paid' => $billing->dueAmount() === 0,
            'prepared_by' => $billing->creator?->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function customerCopy(Billing $billing): array
    {
        return [
            'items' => $this->lines($billing->items, withPrice: true),
            'item_total' => $this->taka($billing->subtotal_amount),
            'discount' => $this->taka($billing->discount_amount),
            'rounding' => $this->rounding((int) $billing->rounding_amount),
            'payable' => $this->taka($billing->amount),
            'paid' => $this->taka($billing->paid_amount),
            'due' => $this->taka($billing->dueAmount()),
            'in_words' => $this->takaInWords->format($billing->amount),
            'initial_collection' => $billing->paid_amount === 0 ? null : [
                'by' => mb_strtoupper((string) $billing->creator?->name),
                'amount' => $this->taka($billing->paid_amount),
            ],
        ];
    }

    /**
     * One Non Lab Copy per department, in the order the department first appears on the bill.
     *
     * @return list<array{department: string, items: list<array<string, ?string>>}>
     */
    private function departmentCopies(Billing $billing): array
    {
        return $billing->items
            ->groupBy(fn (BillingItem $item): int => $item->testItem->department_id)
            ->map(function (Collection $items): array {
                $department = $items->first()->testItem->department;
                $name = $department?->name ?? $items->first()->testItem->department_name;

                return [
                    'department' => $department?->room_no === null ? $name : "{$name} (Room No. {$department->room_no})",
                    'items' => $this->lines($items, withPrice: false),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  iterable<BillingItem>  $items
     * @return list<array<string, ?string>>
     */
    private function lines(iterable $items, bool $withPrice): array
    {
        $lines = [];

        foreach ($items as $item) {
            $lines[] = [
                'code' => $item->testItem->code,
                'name' => $item->testItem->name.($item->quantity > 1 ? " ×{$item->quantity}" : ''),
                'price' => $withPrice ? $this->taka($item->amount) : null,
            ];
        }

        return $lines;
    }

    /**
     * "(X111772) DR. MD. SAIFUL ISLAM, Mirpur, Dhaka": the doctor's code, name and address.
     */
    private function doctor(?Doctor $doctor): ?string
    {
        if ($doctor === null) {
            return null;
        }

        return trim("({$doctor->code}) ".mb_strtoupper($doctor->name).($doctor->address ? ", {$doctor->address}" : ''));
    }

    /**
     * The rounding line, printed only when the payable was rounded: "7.75", or "-0.50" when rounded down.
     */
    private function rounding(int $paisa): ?string
    {
        if ($paisa === 0) {
            return null;
        }

        return ($paisa < 0 ? '-' : '').$this->taka(abs($paisa));
    }

    private function taka(int $paisa): string
    {
        return number_format($paisa / 100, 2);
    }
}
