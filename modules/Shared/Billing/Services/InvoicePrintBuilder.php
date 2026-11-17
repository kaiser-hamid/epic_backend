<?php

namespace Modules\Shared\Billing\Services;

use Modules\Shared\Billing\Models\Billing;
use Modules\Shared\Billing\Models\BillingItem;
use Modules\Shared\Billing\Queries\ShowInvoice;
use Modules\Shared\Doctor\Models\Doctor;
use Modules\Shared\Printing\Contracts\PdfRenderer;
use Modules\Shared\Printing\Data\PdfDocument;
use Modules\Shared\Printing\Data\PdfPageSetup;
use Modules\Shared\TestCatalog\Enums\DepartmentType;
use Modules\Shared\TestCatalog\Models\DepartmentRoom;

/**
 * Builds the bill PDF printed at the counter, laid out like the paper bill and printed on the pre-printed pad
 * (A5, 148 × 210 mm; the top and bottom 27 mm hold the pad's own header and QR footer, so nothing is drawn there).
 *
 * One print is the Customer Copy (every item with its price, the totals, the payment and the amount in words),
 * then one Lab Copy with every lab item (Pathology, the branch's sample collection room), then one Non-Lab Copy per
 * non-lab department on the bill (its room in the bill's branch). The later copies repeat the header and list
 * their items without prices. Recording the print is the caller's job.
 */
final class InvoicePrintBuilder
{
    private const string LAB_DEPARTMENT = 'Pathology (Sample Collection Zone)';

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
        $billing = $this->showInvoice->get($billing)->loadMissing(['branch', 'items.testItem.department']);

        $content = $this->pdfRenderer->render('billing::invoice', [
            'header' => $this->header($billing, $isDuplicate, $isPreview),
            'customerCopy' => $this->customerCopy($billing),
            'copies' => $this->copies($billing),
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
     * The copies after the Customer Copy: the Lab Copy when the bill has lab items, then one Non-Lab Copy per
     * non-lab department in the order the department first appears on the bill. An item whose department is gone
     * counts as lab.
     *
     * @return list<array{title: string, department: string, items: list<array<string, ?string>>}>
     */
    private function copies(Billing $billing): array
    {
        [$nonLabItems, $labItems] = $billing->items
            ->partition(fn (BillingItem $item): bool => $item->testItem->department?->type === DepartmentType::NonLab);

        $copies = [];

        if ($labItems->isNotEmpty()) {
            $copies[] = [
                'title' => 'Lab Copy',
                'department' => $this->withRoom(self::LAB_DEPARTMENT, $billing->branch?->lab_room_no),
                'items' => $this->lines($labItems, withPrice: false),
            ];
        }

        if ($nonLabItems->isEmpty()) {
            return $copies;
        }

        $rooms = DepartmentRoom::query()->where('branch_id', $billing->branch_id)->pluck('room_no', 'department_id');

        foreach ($nonLabItems->groupBy(fn (BillingItem $item): int => $item->testItem->department_id) as $departmentId => $items) {
            $copies[] = [
                'title' => 'Non-Lab Copy',
                'department' => $this->withRoom($items->first()->testItem->department->name, $rooms->get($departmentId)),
                'items' => $this->lines($items, withPrice: false),
            ];
        }

        return $copies;
    }

    /**
     * "X-Ray, Room No. 105", or just the name when the branch has no room for it.
     */
    private function withRoom(string $name, ?string $roomNo): string
    {
        return $roomNo === null ? $name : "{$name}, Room No. {$roomNo}";
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
