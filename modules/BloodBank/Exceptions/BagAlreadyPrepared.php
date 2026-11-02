<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class BagAlreadyPrepared extends DomainException
{
    public function __construct()
    {
        parent::__construct('Units have already been prepared from this blood bag.');
    }

    public function errorCode(): string
    {
        return 'BAG_ALREADY_PREPARED';
    }

    public function status(): int
    {
        return 409;
    }
}
