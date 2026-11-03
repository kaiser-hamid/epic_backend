<?php

namespace Modules\BloodBank\Data;

use Modules\BloodBank\Models\Crossmatch;

/**
 * One unit reserved for or issued to the case: the unit (as UnitResource renders it), its current crossmatch
 * (null until issued), how often its Compatibility Test Report was printed, and whether it can be issued now.
 */
final readonly class BloodIssueUnitRow
{
    public function __construct(
        public UnitView $unit,
        public ?Crossmatch $crossmatch,
        public int $reportPrintCount,
        public bool $canIssue,
    ) {}
}
