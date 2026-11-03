<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class UnitExpired extends DomainException
{
    public function __construct()
    {
        parent::__construct('The unit has expired and cannot be issued.');
    }

    public function errorCode(): string
    {
        return 'UNIT_EXPIRED';
    }
}
