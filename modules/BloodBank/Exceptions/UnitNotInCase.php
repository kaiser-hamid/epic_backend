<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class UnitNotInCase extends DomainException
{
    public function __construct()
    {
        parent::__construct('The unit was not prepared from a blood bag of this case.');
    }

    public function errorCode(): string
    {
        return 'UNIT_NOT_IN_CASE';
    }
}
