<?php

namespace Modules\BloodBank\Enums;

enum BloodBankAuditAction: string
{
    case CaseRegistered = 'bb.case.registered';
    case DonorRegistered = 'bb.donor.registered';
}
