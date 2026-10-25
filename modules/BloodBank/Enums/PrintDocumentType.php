<?php

namespace Modules\BloodBank\Enums;

use Illuminate\Support\Str;

/**
 * Values are prefixed `bb.` because they share `sh_print_logs` with other modules.
 */
enum PrintDocumentType: string
{
    case SampleLabel = 'bb.sample_label';
    case BagLabel = 'bb.bag_label';
    case ComponentLabel = 'bb.component_label';
    case Report = 'bb.report';
    case CompatibilityReport = 'bb.compatibility_report';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', Str::after($this->value, 'bb.')));
    }
}
