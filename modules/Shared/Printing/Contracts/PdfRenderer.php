<?php

namespace Modules\Shared\Printing\Contracts;

use Modules\Shared\Printing\Data\PdfPageSetup;

/**
 * Renders a Blade view to PDF bytes for any module. Pages are split inside the view with `<pagebreak />`.
 * Recording the print is the caller's job (PrintLogger).
 */
interface PdfRenderer
{
    /**
     * @param  array<string, mixed>  $data
     * @return string the PDF file content
     */
    public function render(string $view, array $data = [], PdfPageSetup $page = new PdfPageSetup): string;
}
