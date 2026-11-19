<?php

namespace Modules\BloodBank\Enums;

use Modules\Shared\Rbac\Contracts\RoleTemplateDefinition;

/**
 * Default Blood Bank role templates, created once by `permissions:sync`; admins may then edit or delete them.
 */
enum BloodBankRoleTemplate: string implements RoleTemplateDefinition
{
    case CaseDesk = 'Case desk';
    case SampleCollection = 'Sample collection';
    case Physician = 'Physician';
    case Serology = 'Serology';
    case BloodCollection = 'Blood collection';
    case Units = 'Units';
    case BloodIssue = 'Blood issue';

    public function description(): string
    {
        return match ($this) {
            self::CaseDesk => 'Find, edit and close Blood Bank cases and add their donors',
            self::SampleCollection => 'Collect patient and donor samples and print their labels',
            self::Physician => 'Examine donors and accept or defer them',
            self::Serology => 'Record donor serology results',
            self::BloodCollection => 'Draw blood from eligible donors into patient and storage units, print their labels and record therapeutic phlebotomies',
            self::Units => 'Search blood units and reprint their labels',
            self::BloodIssue => 'Issue blood to cases and print the Compatibility Test Report',
        };
    }

    /**
     * @return list<BloodBankPermission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::CaseDesk => [BloodBankPermission::CaseView, BloodBankPermission::CaseUpdate, BloodBankPermission::CaseClose, BloodBankPermission::DonorView],
            self::SampleCollection => [BloodBankPermission::CaseView, BloodBankPermission::SampleView, BloodBankPermission::SampleCollect, BloodBankPermission::SampleLabelPrint],
            self::Physician => [BloodBankPermission::DonorView, BloodBankPermission::DonorExamView, BloodBankPermission::DonorExamRecord],
            self::Serology => [BloodBankPermission::SerologyView, BloodBankPermission::SerologyRecord],
            self::BloodCollection => [BloodBankPermission::CollectionView, BloodBankPermission::CollectionCollect, BloodBankPermission::PhlebotomyRecord, BloodBankPermission::UnitView, BloodBankPermission::UnitLabelPrint],
            self::Units => [BloodBankPermission::UnitView, BloodBankPermission::UnitLabelPrint],
            self::BloodIssue => [BloodBankPermission::IssueView, BloodBankPermission::IssueRecord, BloodBankPermission::IssueReportPrint],
        };
    }
}
