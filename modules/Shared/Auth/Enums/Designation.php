<?php

namespace Modules\Shared\Auth\Enums;

/**
 * A staff member's job title. Stored on `users.designation`.
 */
enum Designation: string
{
    case Receptionist = 'receptionist';
    case Cashier = 'cashier';
    case LabTechnician = 'lab_technician';
    case MedicalTechnologist = 'medical_technologist';
    case BloodBankTechnician = 'blood_bank_technician';
    case Phlebotomist = 'phlebotomist';
    case Pathologist = 'pathologist';
    case MedicalOfficer = 'medical_officer';
    case Nurse = 'nurse';
    case LabManager = 'lab_manager';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
