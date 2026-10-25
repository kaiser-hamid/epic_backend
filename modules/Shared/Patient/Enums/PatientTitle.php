<?php

namespace Modules\Shared\Patient\Enums;

enum PatientTitle: string
{
    case Mr = 'mr';
    case Mrs = 'mrs';
    case Miss = 'miss';
    case Ms = 'ms';
    case Master = 'master';
    case Baby = 'baby';
    case Dr = 'dr';

    public function label(): string
    {
        return ucfirst($this->value).'.';
    }
}
