<?php

namespace Modules\BloodBank\Enums;

enum CaseDonorStatus: string
{
    case Registered = 'registered';
    case Eligible = 'eligible';
    case Deferred = 'deferred';
    case Collected = 'collected';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
