<?php

namespace Modules\BloodBank\Enums;

enum TestResultType: string
{
    case Text = 'text';
    case Number = 'number';
    case Option = 'option';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
