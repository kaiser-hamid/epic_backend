<?php

namespace Modules\BloodBank\Enums;

/**
 * The physician's Accept / Defer on the serology form. A manual input; only Accept is restricted (never with a
 * reactive screening result).
 */
enum SerologyDecision: string
{
    case Accepted = 'accepted';
    case Deferred = 'deferred';

    public function caseDonorStatus(): CaseDonorStatus
    {
        return match ($this) {
            self::Accepted => CaseDonorStatus::Eligible,
            self::Deferred => CaseDonorStatus::Deferred,
        };
    }

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
