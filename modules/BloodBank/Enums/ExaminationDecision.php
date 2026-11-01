<?php

namespace Modules\BloodBank\Enums;

/**
 * The physician's Accept / Defer on the physical examination form. A manual input, never derived from the
 * measured values.
 */
enum ExaminationDecision: string
{
    case Accepted = 'accepted';
    case Deferred = 'deferred';

    public function caseDonorStatus(): CaseDonorStatus
    {
        return match ($this) {
            self::Accepted => CaseDonorStatus::Fit,
            self::Deferred => CaseDonorStatus::Deferred,
        };
    }

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
