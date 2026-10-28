<?php

namespace Modules\Shared\Billing\Services;

use Modules\Shared\Billing\Contracts\InvoiceDirectory;
use Modules\Shared\Billing\Data\InvoiceSummary;
use Modules\Shared\Billing\Models\Billing;

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

    public function idForInvoiceNo(string $invoiceNo): ?int
    {
        return Billing::query()->where('invoice_no', $invoiceNo)->value('id');
    }
}
