<?php

namespace Modules\BloodBank\Enums;

use Modules\Shared\Rbac\Contracts\PermissionDefinition;

enum BloodBankPermission: string implements PermissionDefinition
{
    case CaseView = 'bb.case.view';
    case CaseUpdate = 'bb.case.update';
    case DonorView = 'bb.donor.view';
    case SampleView = 'bb.sample.view';
    case SampleCollect = 'bb.sample.collect';
    case SampleLabelPrint = 'bb.sample.print_label';
    case DonorExamView = 'bb.donor_exam.view';
    case DonorExamRecord = 'bb.donor_exam.record';
    case SerologyView = 'bb.serology.view';
    case SerologyRecord = 'bb.serology.record';
    case CollectionView = 'bb.collection.view';
    case CollectionCollect = 'bb.collection.collect';
    case CollectionLabelPrint = 'bb.collection.print_label';
    case UnitView = 'bb.unit.view';
    case UnitPrepare = 'bb.unit.prepare';
    case UnitLabelPrint = 'bb.unit.print_label';
    case IssueView = 'bb.issue.view';
    case IssueRecord = 'bb.issue.record';
    case IssueReportPrint = 'bb.issue.print_report';

    public function module(): string
    {
        return 'blood-bank';
    }

    public function moduleLabel(): string
    {
        return 'Blood Bank';
    }

    public function moduleOrder(): int
    {
        return 10;
    }

    public function group(): string
    {
        return match ($this) {
            self::DonorView => 'Donors',
            self::SampleView, self::SampleCollect, self::SampleLabelPrint => 'Samples',
            self::DonorExamView, self::DonorExamRecord => 'Donor Examination',
            self::SerologyView, self::SerologyRecord => 'Donor Serology',
            self::CollectionView, self::CollectionCollect, self::CollectionLabelPrint => 'Blood Collection',
            self::UnitView, self::UnitPrepare, self::UnitLabelPrint => 'Component Preparation',
            self::IssueView, self::IssueRecord, self::IssueReportPrint => 'Blood Issue',
            default => 'Cases',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::CaseView => 'Search and view Blood Bank cases',
            self::CaseUpdate => 'Edit a case\'s details (care of, cause of transfusion, note) and add donors to it',
            self::DonorView => 'Search and view donors',
            self::SampleView => 'Open the sample collection work list and search samples',
            self::SampleCollect => 'Collect patient and donor samples',
            self::SampleLabelPrint => 'Print and reprint sample labels (a reprint needs a reason)',
            self::DonorExamView => 'Open the donor physical examination work list',
            self::DonorExamRecord => 'Record a donor\'s physical examination and accept or defer the donor (physician)',
            self::SerologyView => 'Open the donor serology work list',
            self::SerologyRecord => 'Record a donor\'s serological investigation and accept or defer the donor',
            self::CollectionView => 'Open the blood collection work list and search collected bags',
            self::CollectionCollect => 'Collect blood from an eligible donor into a bag',
            self::CollectionLabelPrint => 'Print and reprint bag labels (a reprint needs a reason)',
            self::UnitView => 'Open the component preparation work list and search blood units in stock',
            self::UnitPrepare => 'Prepare whole blood or components from a bag and store them',
            self::UnitLabelPrint => 'Print and reprint component labels (a reprint needs a reason)',
            self::IssueView => 'Open the blood issue (Compatibility Test Report) work list and case view',
            self::IssueRecord => 'Record the patient blood group, crossmatch and post-donation vitals, and issue blood to the patient',
            self::IssueReportPrint => 'Reprint Compatibility Test Reports (a reprint needs a reason)',
        };
    }
}
