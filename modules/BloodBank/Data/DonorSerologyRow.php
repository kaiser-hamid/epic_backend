<?php

namespace Modules\BloodBank\Data;

use Modules\BloodBank\Models\CaseDonor;
use Modules\BloodBank\Models\Sample;

/**
 * One donor row of the serology screen: the case donor (with `donor` and `assessor` loaded), its donor sample
 * (with `results.testDefinition` and `results.tester` loaded; null until collected) and whether the form can be
 * saved now.
 */
final readonly class DonorSerologyRow
{
    public function __construct(
        public CaseDonor $caseDonor,
        public ?Sample $sample,
        public bool $canRecord,
    ) {}
}
