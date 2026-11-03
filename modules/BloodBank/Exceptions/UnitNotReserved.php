<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class UnitNotReserved extends DomainException
{
    public function __construct()
    {
        parent::__construct('The unit is not reserved for the patient; it may already be issued.');
    }

    public function errorCode(): string
    {
        return 'UNIT_NOT_RESERVED';
    }

    public function status(): int
    {
        return 409;
    }
}
