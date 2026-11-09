<?php

namespace Modules\Shared\Billing\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Billing\Data\PrintInvoiceData;
use Modules\Shared\Billing\Enums\BillingPrintDocumentType;
use Modules\Shared\Billing\Models\Billing;
use Modules\Shared\Billing\Queries\ShowInvoice;
use Modules\Shared\Printing\Contracts\PdfRenderer;
use Modules\Shared\Printing\Contracts\PrintLogger;
use Modules\Shared\Printing\Data\PdfDocument;
use Modules\Shared\Printing\Exceptions\ReprintReasonRequired;

/**
 * Prints a bill as an A4 PDF and logs the print; a reprint needs a reason and is marked "REPRINT". The PDF is
 * rendered inside the transaction, so a failed render logs nothing.
 */
final class PrintInvoice
{
    public function __construct(
        private readonly PrintLogger $printLogger,
        private readonly PdfRenderer $pdfRenderer,
        private readonly ShowInvoice $showInvoice,
    ) {}

    /**
     * @throws ReprintReasonRequired
     */
    public function handle(User $actor, Billing $billing, PrintInvoiceData $data): PdfDocument
    {
        return DB::transaction(function () use ($actor, $billing, $data): PdfDocument {
            $printLog = $this->printLogger->record(BillingPrintDocumentType::Invoice, $billing, $actor, $data->reprintReason);

            $content = $this->pdfRenderer->render('billing::invoice', [
                'billing' => $this->showInvoice->get($billing),
                'isReprint' => $printLog->is_reprint,
                'printedBy' => $actor->name,
                'printedAt' => now(),
            ]);

            return new PdfDocument($content, "INV-{$billing->invoice_no}.pdf");
        });
    }
}
