<?php

namespace Modules\BloodBank\Enums;

enum StorageLocationType: string
{
    case Refrigerator = 'refrigerator';
    case Freezer = 'freezer';
    case PlateletAgitator = 'platelet_agitator';
    case Other = 'other';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
