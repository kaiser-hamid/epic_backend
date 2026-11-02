<?php

namespace Modules\BloodBank\Enums;

enum BloodBankAuditAction: string
{
    case CaseRegistered = 'bb.case.registered';
    case DonorRegistered = 'bb.donor.registered';
    case SampleCollected = 'bb.sample.collected';
    case DonorExamined = 'bb.donor.examined';
    case DonorExaminationAmended = 'bb.donor.examination_amended';
    case DonorSerologyRecorded = 'bb.donor.serology_recorded';
    case DonorSerologyAmended = 'bb.donor.serology_amended';
}
