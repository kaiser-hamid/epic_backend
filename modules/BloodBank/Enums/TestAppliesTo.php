<?php

namespace Modules\BloodBank\Enums;

enum TestAppliesTo: string
{
    case Patient = 'patient';
    case Donor = 'donor';
    case Both = 'both';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
