<?php

namespace Modules\BloodBank\Enums;

/**
 * `held`: a stock unit sampled for a case (`case_id` set) while its serology is pending; no other case can take
 * it. It becomes `reserved` when its patient label is printed, or goes back to `available` (or `quarantined`).
 */
enum UnitStatus: string
{
    case Available = 'available';
    case Held = 'held';
    case Reserved = 'reserved';
    case Issued = 'issued';
    case Quarantined = 'quarantined';
    case Discarded = 'discarded';
    case Expired = 'expired';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
