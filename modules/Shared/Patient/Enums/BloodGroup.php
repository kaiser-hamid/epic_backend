<?php

namespace Modules\Shared\Patient\Enums;

enum BloodGroup: string
{
    case APositive = 'A_POS';
    case ANegative = 'A_NEG';
    case BPositive = 'B_POS';
    case BNegative = 'B_NEG';
    case ABPositive = 'AB_POS';
    case ABNegative = 'AB_NEG';
    case OPositive = 'O_POS';
    case ONegative = 'O_NEG';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
