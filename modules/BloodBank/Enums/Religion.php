<?php

namespace Modules\BloodBank\Enums;

enum Religion: string
{
    case Islam = 'islam';
    case Hinduism = 'hinduism';
    case Buddhism = 'buddhism';
    case Christianity = 'christianity';
    case Other = 'other';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
