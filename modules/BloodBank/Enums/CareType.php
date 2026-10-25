<?php

namespace Modules\BloodBank\Enums;

enum CareType: string
{
    case Daycare = 'daycare';
    case Out = 'out';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
