<?php

namespace Modules\BloodBank\Policies;

use Modules\BloodBank\Enums\BloodBankPermission;
use Modules\BloodBank\Models\PatientCase;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Rbac\Contracts\AccessChecker;

/**
 * A case is visible only from the branch that registered it.
 */
final class PatientCasePolicy
{
    public function __construct(private readonly AccessChecker $accessChecker) {}

    public function view(User $user, PatientCase $case): bool
    {
        return $this->accessChecker->allowsOnRecord($user, BloodBankPermission::CaseView, $case->branch_id);
    }

    public function addDonor(User $user, PatientCase $case): bool
    {
        return $this->accessChecker->allowsOnRecord($user, BloodBankPermission::CaseCreate, $case->branch_id);
    }

    public function viewSamples(User $user, PatientCase $case): bool
    {
        return $this->accessChecker->allowsOnRecord($user, BloodBankPermission::SampleView, $case->branch_id);
    }

    public function collectSamples(User $user, PatientCase $case): bool
    {
        return $this->accessChecker->allowsOnRecord($user, BloodBankPermission::SampleCollect, $case->branch_id);
    }

    public function printSampleLabels(User $user, PatientCase $case): bool
    {
        return $this->accessChecker->allowsOnRecord($user, BloodBankPermission::SampleLabelPrint, $case->branch_id);
    }

    public function viewDonorExamination(User $user, PatientCase $case): bool
    {
        return $this->accessChecker->allowsOnRecord($user, BloodBankPermission::DonorExamView, $case->branch_id);
    }

    public function examineDonor(User $user, PatientCase $case): bool
    {
        return $this->accessChecker->allowsOnRecord($user, BloodBankPermission::DonorExamRecord, $case->branch_id);
    }

    public function viewDonorSerology(User $user, PatientCase $case): bool
    {
        return $this->accessChecker->allowsOnRecord($user, BloodBankPermission::SerologyView, $case->branch_id);
    }

    public function recordDonorSerology(User $user, PatientCase $case): bool
    {
        return $this->accessChecker->allowsOnRecord($user, BloodBankPermission::SerologyRecord, $case->branch_id);
    }

    public function viewBloodCollection(User $user, PatientCase $case): bool
    {
        return $this->accessChecker->allowsOnRecord($user, BloodBankPermission::CollectionView, $case->branch_id);
    }

    public function collectBlood(User $user, PatientCase $case): bool
    {
        return $this->accessChecker->allowsOnRecord($user, BloodBankPermission::CollectionCollect, $case->branch_id);
    }

    public function printBagLabels(User $user, PatientCase $case): bool
    {
        return $this->accessChecker->allowsOnRecord($user, BloodBankPermission::CollectionLabelPrint, $case->branch_id);
    }

    public function viewUnits(User $user, PatientCase $case): bool
    {
        return $this->accessChecker->allowsOnRecord($user, BloodBankPermission::UnitView, $case->branch_id);
    }

    public function prepareUnits(User $user, PatientCase $case): bool
    {
        return $this->accessChecker->allowsOnRecord($user, BloodBankPermission::UnitPrepare, $case->branch_id);
    }

    public function printUnitLabels(User $user, PatientCase $case): bool
    {
        return $this->accessChecker->allowsOnRecord($user, BloodBankPermission::UnitLabelPrint, $case->branch_id);
    }
}
