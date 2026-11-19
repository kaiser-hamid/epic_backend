<?php

namespace Modules\BloodBank\Data;

/**
 * Whether the case details page offers "Close case" (`canClose`: open and no unit reserved) and whether closing
 * needs a reason (billed work outstanding).
 */
final readonly class CaseClosingView
{
    public function __construct(
        public bool $canClose,
        public bool $needsReason,
    ) {}
}
