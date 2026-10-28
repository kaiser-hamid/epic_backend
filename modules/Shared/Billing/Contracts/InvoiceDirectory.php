<?php

namespace Modules\Shared\Billing\Contracts;

use Modules\Shared\Billing\Data\InvoiceSummary;

/**
 * Read access to invoices for other modules, which store only `billing_id`.
 */
interface InvoiceDirectory
{
    /**
     * @param  list<int>  $ids
     * @return array<int, InvoiceSummary> keyed by billing id; unknown ids are left out
     */
    public function summaries(array $ids): array;

    /**
     * The billing id for a scanned or typed invoice number, in any branch.
     */
    public function idForInvoiceNo(string $invoiceNo): ?int;
}
