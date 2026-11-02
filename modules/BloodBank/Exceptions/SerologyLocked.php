<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class SerologyLocked extends DomainException
{
    public function __construct()
    {
        parent::__construct('The blood bag has been collected; the serology can no longer be changed.');
    }

    public function errorCode(): string
    {
        return 'SEROLOGY_LOCKED';
    }

    public function status(): int
    {
        return 409;
    }
}
