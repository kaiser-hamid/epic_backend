<?php

namespace Modules\BloodBank\Enums;

enum SampleStatus: string
{
    case Collected = 'collected';
    case InTesting = 'in_testing';
    case Tested = 'tested';
    case Rejected = 'rejected';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
