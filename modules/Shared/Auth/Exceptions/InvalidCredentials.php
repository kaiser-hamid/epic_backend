<?php

namespace Modules\Shared\Auth\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class InvalidCredentials extends DomainException
{
    public function __construct()
    {
        parent::__construct('The username or password is incorrect.');
    }

    public function errorCode(): string
    {
        return 'INVALID_CREDENTIALS';
    }

    public function status(): int
    {
        return 401;
    }
}
