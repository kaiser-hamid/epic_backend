<?php

namespace Modules\BloodBank\Enums;

use Illuminate\Support\Str;

/**
 * Values are prefixed `bb.` because they share `sh_print_logs` with other modules. `ComponentLabel` is the label of
 * a unit: the patient label for a unit reserved for or issued to a patient, the storage label for stock.
 */
enum PrintDocumentType: string
{
    case SampleLabel = 'bb.sample_label';
    case ComponentLabel = 'bb.component_label';
    case Report = 'bb.report';
    case CompatibilityReport = 'bb.compatibility_report';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', Str::after($this->value, 'bb.')));
    }
}
