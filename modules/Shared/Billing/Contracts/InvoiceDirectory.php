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
     * The test item codes billed on each invoice, in billing order.
     *
     * @param  list<int>  $ids
     * @return array<int, list<string>> keyed by billing id; unknown ids and invoices without items are left out
     */
    public function testItemCodes(array $ids): array;

    /**
     * The billing id for a scanned or typed invoice number, in any branch.
     */
    public function idForInvoiceNo(string $invoiceNo): ?int;

    /**
     * The (not deleted) invoice with this number, in any branch, e.g. a bill staff attach to a Blood Bank donor.
     */
    public function findByInvoiceNo(string $invoiceNo): ?InvoiceSummary;
}
