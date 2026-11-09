<?php

namespace Modules\Shared\Billing\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Billing\Data\PrintInvoiceData;
use Modules\Shared\Billing\Enums\BillingPrintDocumentType;
use Modules\Shared\Billing\Models\Billing;
use Modules\Shared\Billing\Services\InvoicePrintBuilder;
use Modules\Shared\Printing\Contracts\PrintLogger;
use Modules\Shared\Printing\Data\PdfDocument;
use Modules\Shared\Printing\Exceptions\ReprintReasonRequired;

/**
 * Prints a bill (Customer Copy plus a Non Lab Copy per department) and logs the print. The first print is the
 * "Original Copy"; every later one is a "Duplicate Copy" and needs a reason. The PDF is rendered inside the
 * transaction, so a failed render logs nothing.
 */
final class PrintInvoice
{
    public function __construct(
        private readonly PrintLogger $printLogger,
        private readonly InvoicePrintBuilder $builder,
    ) {}

    /**
     * @throws ReprintReasonRequired
     */
    public function handle(User $actor, Billing $billing, PrintInvoiceData $data): PdfDocument
    {
        return DB::transaction(function () use ($actor, $billing, $data): PdfDocument {
            $printLog = $this->printLogger->record(BillingPrintDocumentType::Invoice, $billing, $actor, $data->reprintReason);

            return $this->builder->build($billing, isDuplicate: $printLog->is_reprint);
        });
    }
}
