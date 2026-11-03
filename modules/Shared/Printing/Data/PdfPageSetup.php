<?php

namespace Modules\Shared\Printing\Data;

/**
 * Paper size and margins of a PDF, in millimetres. The default is A4 with 12 mm margins. `marginHeader` /
 * `marginFooter` are the distances of an HTML page header / footer from the paper's top / bottom edge.
 */
final readonly class PdfPageSetup
{
    public function __construct(
        public float $width = 210,
        public float $height = 297,
        public float $marginTop = 12,
        public float $marginBottom = 12,
        public float $marginLeft = 12,
        public float $marginRight = 12,
        public float $marginHeader = 9,
        public float $marginFooter = 9,
    ) {}
}
