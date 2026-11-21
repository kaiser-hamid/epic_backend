<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class UnitNotNeeded extends DomainException
{
    public function __construct()
    {
        parent::__construct('The case needs no more units of this component.');
    }

    public function errorCode(): string
    {
        return 'UNIT_NOT_NEEDED';
    }

    public function status(): int
    {
        return 422;
    }
}
