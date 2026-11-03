<?php

namespace Modules\BloodBank\Enums;

enum BloodBankAuditAction: string
{
    case CaseRegistered = 'bb.case.registered';
    case CaseDonorAdded = 'bb.case.donor_added';
    case DonorRegistered = 'bb.donor.registered';
    case SampleCollected = 'bb.sample.collected';
    case DonorExamined = 'bb.donor.examined';
    case DonorExaminationAmended = 'bb.donor.examination_amended';
    case DonorSerologyRecorded = 'bb.donor.serology_recorded';
    case DonorSerologyAmended = 'bb.donor.serology_amended';
    case BloodCollected = 'bb.blood.collected';
    case UnitsPrepared = 'bb.units.prepared';
    case PatientBloodGrouped = 'bb.patient.blood_grouped';
    case PatientBloodGroupAmended = 'bb.patient.blood_group_amended';
    case CrossmatchRecorded = 'bb.crossmatch.recorded';
    case DonorPostVitalsRecorded = 'bb.donor.post_vitals_recorded';
    case BloodIssued = 'bb.blood.issued';
    case CaseClosed = 'bb.case.closed';
}
