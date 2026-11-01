<?php

namespace Modules\BloodBank\Data;

use Modules\BloodBank\Models\CaseDonor;

/**
 * One donor row of the examination screen: the case donor (with `donor`, `assessor`, `screening.examiner`
 * loaded) and whether the form can be saved now.
 */
final readonly class DonorExaminationRow
{
    public function __construct(
        public CaseDonor $caseDonor,
        public bool $canExamine,
    ) {}
}
