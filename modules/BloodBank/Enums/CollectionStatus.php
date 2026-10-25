<?php

namespace Modules\BloodBank\Enums;

enum CollectionStatus: string
{
    case Collected = 'collected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
