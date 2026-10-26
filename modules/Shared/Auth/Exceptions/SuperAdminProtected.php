<?php

namespace Modules\Shared\Auth\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class SuperAdminProtected extends DomainException
{
    public function __construct()
    {
        parent::__construct('Only a super admin can change a super admin account.');
    }

    public function errorCode(): string
    {
        return 'SUPER_ADMIN_PROTECTED';
    }

    public function status(): int
    {
        return 403;
    }
}
