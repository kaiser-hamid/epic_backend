<?php

namespace Modules\BloodBank\Services;

use Modules\BloodBank\Enums\CaseDonorStatus;
use Modules\BloodBank\Enums\SampleStatus;
use Modules\BloodBank\Models\CaseDonor;
use Modules\BloodBank\Models\Sample;

/**
 * The physical examination closes once the donor sample is drawn (a rejected sample does not count) or the
 * donor has moved past it (serology accepted, or bled). Until then the physician may correct the form. A stock
 * row has no physical exam at all (it is `fit` from the start).
 */
final class DonorExaminationLock
{
    public function isLocked(CaseDonor $caseDonor): bool
    {
        if ($caseDonor->isStock() || in_array($caseDonor->status, [CaseDonorStatus::Eligible, CaseDonorStatus::Collected], true)) {
            return true;
        }

        $caseDonor->loadMissing('samples');

        return $caseDonor->samples->contains(fn (Sample $sample): bool => $sample->status !== SampleStatus::Rejected);
    }
}
