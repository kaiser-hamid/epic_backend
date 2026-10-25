<?php

namespace Modules\BloodBank\Enums;

enum TestResultStatus: string
{
    case Entered = 'entered';
    case Reviewed = 'reviewed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
