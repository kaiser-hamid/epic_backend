<?php

namespace Modules\BloodBank\Data;

use Modules\BloodBank\Models\CaseDonor;
use Modules\BloodBank\Models\Collection;

/**
 * One donor row of the blood collection screen: the case donor (with `donor` loaded), its bag (with `collector`
 * loaded; null until collected) and whether blood can be collected now. A stock row has no bag; instead
 * `canReserveStockUnit` says whether its patient label can be printed.
 */
final readonly class BloodCollectionRow
{
    public function __construct(
        public CaseDonor $caseDonor,
        public ?Collection $collection,
        public bool $isCollectable,
        public bool $canReserveStockUnit = false,
    ) {}
}
