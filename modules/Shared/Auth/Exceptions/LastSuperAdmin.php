<?php

namespace Modules\Shared\Auth\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class LastSuperAdmin extends DomainException
{
    public function __construct()
    {
        parent::__construct('At least one active super admin must remain.');
    }

    public function errorCode(): string
    {
        return 'LAST_SUPER_ADMIN';
    }
}
