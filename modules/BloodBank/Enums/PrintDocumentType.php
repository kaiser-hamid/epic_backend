<?php

namespace Modules\BloodBank\Enums;

use Illuminate\Support\Str;

/**
 * Values are prefixed `bb.` because they share `sh_print_logs` with other modules. A unit has two labels: the
 * storage label while it is stock (or held), and the patient label once it is reserved for or issued to a patient.
 * They are separate documents, so a stock unit's first patient label is not a reprint of its storage label.
 */
enum PrintDocumentType: string
{
    case SampleLabel = 'bb.sample_label';
    case StorageLabel = 'bb.storage_label';
    case PatientLabel = 'bb.patient_label';
    case Report = 'bb.report';
    case CompatibilityReport = 'bb.compatibility_report';

    /**
     * The label a unit gets in this status.
     */
    public static function unitLabelFor(UnitStatus $status): self
    {
        return in_array($status, [UnitStatus::Reserved, UnitStatus::Issued], true) ? self::PatientLabel : self::StorageLabel;
    }

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', Str::after($this->value, 'bb.')));
    }
}
