<?php

namespace Modules\BloodBank\Enums;

/**
 * A donor's progress in one case: accepted at the physical exam (`fit`), then at serology (`eligible`), then
 * bled (`collected`). `deferred` can be set at either check.
 */
enum CaseDonorStatus: string
{
    case Registered = 'registered';
    case Fit = 'fit';
    case Eligible = 'eligible';
    case Deferred = 'deferred';
    case Collected = 'collected';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
