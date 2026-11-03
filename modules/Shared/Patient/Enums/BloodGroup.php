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

    /**
     * The ABO group alone: `A`, `B`, `AB` or `O`.
     */
    public function abo(): string
    {
        return strstr($this->value, '_', true);
    }

    /**
     * The Rhesus (D) result as printed on reports: `Positive` or `Negative`.
     */
    public function rhesus(): string
    {
        return str_ends_with($this->value, '_POS') ? 'Positive' : 'Negative';
    }
}
