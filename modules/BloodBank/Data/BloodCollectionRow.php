<?php

namespace Modules\BloodBank\Data;

use Modules\BloodBank\Models\CaseDonor;
use Modules\BloodBank\Models\Collection;

/**
 * One donor row of the blood collection screen: the case donor (with `donor` loaded), its bag (with `collector`
 * loaded; null until collected), whether blood can be collected now, and how often the bag label was printed.
 */
final readonly class BloodCollectionRow
{
    public function __construct(
        public CaseDonor $caseDonor,
        public ?Collection $collection,
        public bool $isCollectable,
        public int $labelPrintCount,
    ) {}
}
