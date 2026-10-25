<?php

namespace Modules\BloodBank\Enums;

enum ConsumableTransactionType: string
{
    case Received = 'received';
    case Issued = 'issued';
    case Adjusted = 'adjusted';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
