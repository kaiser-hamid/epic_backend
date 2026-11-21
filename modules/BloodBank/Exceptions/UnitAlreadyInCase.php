<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class UnitAlreadyInCase extends DomainException
{
    public function __construct()
    {
        parent::__construct('This unit has already been taken for this case.');
    }

    public function errorCode(): string
    {
        return 'UNIT_ALREADY_IN_CASE';
    }

    public function status(): int
    {
        return 409;
    }
}
