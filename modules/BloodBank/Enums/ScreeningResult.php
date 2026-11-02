<?php

namespace Modules\BloodBank\Enums;

enum ScreeningResult: string
{
    case NonReactive = 'non_reactive';
    case Reactive = 'reactive';

    public function label(): string
    {
        return ucwords(str_replace('_', '-', $this->value), '-');
    }
}
