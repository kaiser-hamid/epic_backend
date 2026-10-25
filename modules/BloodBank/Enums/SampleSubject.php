<?php

namespace Modules\BloodBank\Enums;

enum SampleSubject: string
{
    case Patient = 'patient';
    case Donor = 'donor';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
