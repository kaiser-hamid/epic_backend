<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class UnitNotAvailable extends DomainException
{
    public function __construct()
    {
        parent::__construct('The unit is not available in stock; it may be held, reserved or issued for another case, or quarantined.');
    }

    public function errorCode(): string
    {
        return 'UNIT_NOT_AVAILABLE';
    }

    public function status(): int
    {
        return 409;
    }
}
