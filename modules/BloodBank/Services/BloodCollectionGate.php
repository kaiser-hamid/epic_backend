<?php

namespace Modules\BloodBank\Services;

use Modules\BloodBank\Enums\CaseDonorStatus;
use Modules\BloodBank\Enums\CollectionStatus;
use Modules\BloodBank\Exceptions\BagAlreadyCollected;
use Modules\BloodBank\Exceptions\DonorDeferred;
use Modules\BloodBank\Exceptions\DonorNotEligible;
use Modules\BloodBank\Exceptions\DonorNotFit;
use Modules\BloodBank\Models\CaseDonor;
use Modules\BloodBank\Models\Collection;
use Modules\Shared\Support\Exceptions\DomainException;

/**
 * Whether blood can be collected from a case donor: only an `eligible` donor (accepted at the physical exam and
 * at serology) with no bag yet. One bag per case donor; a cancelled bag does not count.
 */
final class BloodCollectionGate
{
    /**
     * The donor's bag, with `collector` loaded; null until collected.
     */
    public function collectionFor(CaseDonor $caseDonor): ?Collection
    {
        $caseDonor->loadMissing('collections.collector');

        return $caseDonor->collections
            ->filter(fn (Collection $collection): bool => $collection->status !== CollectionStatus::Cancelled)
            ->sortByDesc('id')
            ->first();
    }

    /**
     * The reason blood cannot be collected now, or null when it can.
     */
    public function refusal(CaseDonor $caseDonor): ?DomainException
    {
        return match (true) {
            $caseDonor->status === CaseDonorStatus::Collected, $this->collectionFor($caseDonor) !== null => new BagAlreadyCollected,
            $caseDonor->status === CaseDonorStatus::Registered => new DonorNotFit,
            $caseDonor->status === CaseDonorStatus::Fit => new DonorNotEligible,
            $caseDonor->status === CaseDonorStatus::Deferred => new DonorDeferred,
            default => null,
        };
    }
}
