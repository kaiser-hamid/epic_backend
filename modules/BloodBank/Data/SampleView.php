<?php

namespace Modules\BloodBank\Data;

use Modules\BloodBank\Models\Sample;
use Modules\Shared\Printing\Models\PrintLog;

/**
 * A sample (with its case loaded) and who it was drawn from. `labelPrint` is set when the label was just
 * printed. Rendered by SampleResource.
 */
final readonly class SampleView
{
    public function __construct(
        public Sample $sample,
        public SubjectView $subject,
        public ?PrintLog $labelPrint = null,
    ) {}
}
