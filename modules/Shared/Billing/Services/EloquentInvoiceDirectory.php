<?php

namespace Modules\Shared\Billing\Services;

use Illuminate\Support\Collection;
use Modules\Shared\Billing\Contracts\InvoiceDirectory;
use Modules\Shared\Billing\Data\InvoiceSummary;
use Modules\Shared\Billing\Models\Billing;
use Modules\Shared\Billing\Models\BillingItem;

final class EloquentInvoiceDirectory implements InvoiceDirectory
{
    public function summaries(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return Billing::query()
            ->whereKey($ids)
            ->with('referral.doctor')
            ->get()
            ->mapWithKeys(fn (Billing $billing): array => [$billing->id => InvoiceSummary::fromModel($billing)])
            ->all();
    }

    public function testItemCodes(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return BillingItem::query()
            ->join('sh_test_items', 'sh_test_items.id', '=', 'sh_billing_items.test_item_id')
            ->whereIn('sh_billing_items.billing_id', $ids)
            ->orderBy('sh_billing_items.id')
            ->get(['sh_billing_items.billing_id', 'sh_test_items.code'])
            ->groupBy('billing_id')
            ->map(fn (Collection $items): array => $items->pluck('code')->values()->all())
            ->all();
    }

    public function idForInvoiceNo(string $invoiceNo): ?int
    {
        return Billing::query()->where('invoice_no', $invoiceNo)->value('id');
    }

    public function findByInvoiceNo(string $invoiceNo): ?InvoiceSummary
    {
        $billing = Billing::query()->with('referral.doctor')->where('invoice_no', $invoiceNo)->first();

        return $billing === null ? null : InvoiceSummary::fromModel($billing);
    }
}
