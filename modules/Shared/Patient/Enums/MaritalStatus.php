<?php

namespace Modules\Shared\Patient\Enums;

enum MaritalStatus: string
{
    case Single = 'single';
    case Married = 'married';
    case Divorced = 'divorced';
    case Widowed = 'widowed';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
