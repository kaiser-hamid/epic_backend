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
}
