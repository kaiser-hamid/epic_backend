<?php

namespace Modules\BloodBank\Enums;

use Modules\Shared\Rbac\Contracts\PermissionDefinition;

enum BloodBankPermission: string implements PermissionDefinition
{
    case CaseView = 'bb.case.view';
    case CaseCreate = 'bb.case.create';
    case DonorView = 'bb.donor.view';
    case SampleView = 'bb.sample.view';
    case SampleCollect = 'bb.sample.collect';
    case SampleLabelPrint = 'bb.sample.print_label';

    public function module(): string
    {
        return 'blood-bank';
    }

    public function group(): string
    {
        return match ($this) {
            self::DonorView => 'Donors',
            self::SampleView, self::SampleCollect, self::SampleLabelPrint => 'Samples',
            default => 'Cases',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::CaseView => 'Search and view Blood Bank cases',
            self::CaseCreate => 'Register a case: bill the patient, request components and register donors',
            self::DonorView => 'Search and view donors',
            self::SampleView => 'Open the sample collection work list and search samples',
            self::SampleCollect => 'Collect patient and donor samples',
            self::SampleLabelPrint => 'Print and reprint sample labels (a reprint needs a reason)',
        };
    }
}
