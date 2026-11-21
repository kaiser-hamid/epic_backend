<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class UnitNotFound extends DomainException
{
    public function __construct()
    {
        parent::__construct('No blood unit of this branch has this unit no.');
    }

    public function errorCode(): string
    {
        return 'UNIT_NOT_FOUND';
    }

    public function status(): int
    {
        return 404;
    }
}
