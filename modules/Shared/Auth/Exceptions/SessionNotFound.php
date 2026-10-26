<?php

namespace Modules\Shared\Auth\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class SessionNotFound extends DomainException
{
    public function __construct()
    {
        parent::__construct('Session not found.');
    }

    public function errorCode(): string
    {
        return 'SESSION_NOT_FOUND';
    }

    public function status(): int
    {
        return 404;
    }
}
