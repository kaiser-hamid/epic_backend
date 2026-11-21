<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class UnitNotHeld extends DomainException
{
    public function __construct()
    {
        parent::__construct('The stock unit is no longer held for this case.');
    }

    public function errorCode(): string
    {
        return 'UNIT_NOT_HELD';
    }

    public function status(): int
    {
        return 409;
    }
}
