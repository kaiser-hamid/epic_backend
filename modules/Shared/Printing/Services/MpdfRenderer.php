<?php

namespace Modules\Shared\Printing\Services;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Filesystem\Filesystem;
use Modules\Shared\Printing\Contracts\PdfRenderer;
use Modules\Shared\Printing\Data\PdfPageSetup;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * PDFs through mPDF. mPDF caches fonts in a temp directory under storage, created on first use.
 */
final class MpdfRenderer implements PdfRenderer
{
    public function __construct(
        private readonly ViewFactory $views,
        private readonly Filesystem $files,
    ) {}

    public function render(string $view, array $data = [], PdfPageSetup $page = new PdfPageSetup): string
    {
        $tempDir = storage_path('app/mpdf');
        $this->files->ensureDirectoryExists($tempDir);

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => [$page->width, $page->height],
            'tempDir' => $tempDir,
            'margin_top' => $page->marginTop,
            'margin_bottom' => $page->marginBottom,
            'margin_left' => $page->marginLeft,
            'margin_right' => $page->marginRight,
            'margin_header' => $page->marginHeader,
            'margin_footer' => $page->marginFooter,
        ]);

        $mpdf->WriteHTML($this->views->make($view, $data)->render());

        return $mpdf->Output('', Destination::STRING_RETURN);
    }
}
