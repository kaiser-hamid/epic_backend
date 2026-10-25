<?php

namespace Modules\BloodBank\Enums;

enum UnitStatus: string
{
    case Available = 'available';
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
