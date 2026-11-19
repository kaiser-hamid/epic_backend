<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class CaseHasReservedUnits extends DomainException
{
    public function __construct()
    {
        parent::__construct('Units are still reserved for this case; return them to stock before closing it.');
    }

    public function errorCode(): string
    {
        return 'CASE_HAS_RESERVED_UNITS';
    }

    public function status(): int
    {
        return 409;
    }
}
