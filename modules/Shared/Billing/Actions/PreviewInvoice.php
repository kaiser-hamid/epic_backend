<?php

namespace Modules\Shared\Billing\Actions;

use Modules\Shared\Billing\Models\Billing;
use Modules\Shared\Billing\Services\InvoicePrintBuilder;
use Modules\Shared\Printing\Data\PdfDocument;

/**
 * The bill PDF to look at on screen. It is not a print: nothing is logged, and the header says "Preview" in place
 * of "Original Copy" / "Duplicate Copy" so a preview printed from the browser is never taken for a real copy.
 */
final class PreviewInvoice
{
    public function __construct(private readonly InvoicePrintBuilder $builder) {}

    public function handle(Billing $billing): PdfDocument
    {
        return $this->builder->build($billing, isPreview: true);
    }
}
