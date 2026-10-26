<?php

namespace Modules\Shared\Auth\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class CannotChangeOwnAccount extends DomainException
{
    public function __construct()
    {
        parent::__construct('You cannot perform this action on your own account.');
    }

    public function errorCode(): string
    {
        return 'CANNOT_CHANGE_OWN_ACCOUNT';
    }

    public function status(): int
    {
        return 422;
    }
}
