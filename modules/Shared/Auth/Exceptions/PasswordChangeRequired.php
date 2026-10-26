<?php

namespace Modules\Shared\Auth\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class PasswordChangeRequired extends DomainException
{
    public function __construct()
    {
        parent::__construct('You must change your password before continuing.');
    }

    public function errorCode(): string
    {
        return 'PASSWORD_CHANGE_REQUIRED';
    }

    public function status(): int
    {
        return 403;
    }
}
