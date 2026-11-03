<?php

namespace Modules\BloodBank\Data;

use Modules\BloodBank\Models\CaseDonor;
use Modules\BloodBank\Models\Collection;
use Modules\BloodBank\Models\Sample;

/**
 * One case donor on the blood issue screen: the donor (with `donor`, `assessor` and `screening` loaded), the
 * donor sample with its serology results, the bag, and whether the post-donation vitals still have to be entered.
 */
final readonly class BloodIssueDonorRow
{
    public function __construct(
        public CaseDonor $caseDonor,
        public ?Sample $sample,
        public ?Collection $bag,
        public bool $needsPostVitals,
    ) {}
}
