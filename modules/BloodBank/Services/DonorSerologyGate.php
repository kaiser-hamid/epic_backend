<?php

namespace Modules\BloodBank\Services;

use Modules\BloodBank\Enums\CaseDonorStatus;
use Modules\BloodBank\Enums\SampleStatus;
use Modules\BloodBank\Exceptions\DonorDeferred;
use Modules\BloodBank\Exceptions\DonorNotFit;
use Modules\BloodBank\Exceptions\DonorSampleNotCollected;
use Modules\BloodBank\Exceptions\SerologyLocked;
use Modules\BloodBank\Models\CaseDonor;
use Modules\BloodBank\Models\Sample;
use Modules\BloodBank\Models\TestResult;
use Modules\Shared\Support\Exceptions\DomainException;

/**
 * Whether a case donor's serology can be saved: the donor was accepted at the physical exam and the donor sample
 * is collected (a rejected sample does not count). A saved serology can be corrected, including its own Accept /
 * Defer, until the bag is collected. A donor deferred at the physical exam (deferred with no serology) is out.
 */
final class DonorSerologyGate
{
    /**
     * The donor sample the serology is recorded on, with `results` loaded.
     */
    public function sampleFor(CaseDonor $caseDonor): ?Sample
    {
        $caseDonor->loadMissing('samples.results');

        return $caseDonor->samples
            ->filter(fn (Sample $sample): bool => $sample->status !== SampleStatus::Rejected)
            ->sortByDesc('id')
            ->first();
    }

    public function hasSerology(?Sample $sample): bool
    {
        return $sample !== null && $sample->results->contains(fn (TestResult $result): bool => $result->is_current);
    }

    /**
     * The reason the serology cannot be saved now, or null when it can.
     */
    public function refusal(CaseDonor $caseDonor): ?DomainException
    {
        $sample = $this->sampleFor($caseDonor);

        return match (true) {
            $caseDonor->status === CaseDonorStatus::Collected => new SerologyLocked,
            $caseDonor->status === CaseDonorStatus::Registered => new DonorNotFit,
            $caseDonor->status === CaseDonorStatus::Deferred && ! $this->hasSerology($sample) => new DonorDeferred,
            $sample === null => new DonorSampleNotCollected,
            default => null,
        };
    }
}
