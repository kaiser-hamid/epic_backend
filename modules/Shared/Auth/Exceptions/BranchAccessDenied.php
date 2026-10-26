<?php

namespace Modules\Shared\Auth\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class BranchAccessDenied extends DomainException
{
    public function __construct()
    {
        parent::__construct('You do not have access to this branch.');
    }

    public function errorCode(): string
    {
        return 'BRANCH_ACCESS_DENIED';
    }

    public function status(): int
    {
        return 403;
    }
}
