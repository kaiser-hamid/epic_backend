<?php

namespace Modules\Shared\Auth\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class AccountInactive extends DomainException
{
    public function __construct()
    {
        parent::__construct('This account has been deactivated.');
    }

    public function errorCode(): string
    {
        return 'ACCOUNT_INACTIVE';
    }

    public function status(): int
    {
        return 403;
    }
}
