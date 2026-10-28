<?php

namespace Modules\BloodBank\Enums;

use Modules\Shared\Rbac\Contracts\PermissionDefinition;

enum BloodBankPermission: string implements PermissionDefinition
{
    case CaseView = 'bb.case.view';
    case CaseCreate = 'bb.case.create';
    case DonorView = 'bb.donor.view';

    public function module(): string
    {
        return 'blood-bank';
    }

    public function group(): string
    {
        return match ($this) {
            self::DonorView => 'Donors',
            default => 'Cases',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::CaseView => 'Search and view Blood Bank cases',
            self::CaseCreate => 'Register a case: bill the patient, request components and register donors',
            self::DonorView => 'Search and view donors',
        };
    }
}
