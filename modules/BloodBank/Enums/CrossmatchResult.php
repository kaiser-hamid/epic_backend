<?php

namespace Modules\BloodBank\Enums;

enum CrossmatchResult: string
{
    case Compatible = 'compatible';
    case Incompatible = 'incompatible';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
