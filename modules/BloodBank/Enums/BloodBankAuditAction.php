<?php

namespace Modules\BloodBank\Enums;

enum BloodBankAuditAction: string
{
    case CaseRegistered = 'bb.case.registered';
    case DonorRegistered = 'bb.donor.registered';
    case SampleCollected = 'bb.sample.collected';
    case DonorExamined = 'bb.donor.examined';
    case DonorExaminationAmended = 'bb.donor.examination_amended';
}
