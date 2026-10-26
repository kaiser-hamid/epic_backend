<?php

namespace Modules\Shared\Auth\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class BranchInactive extends DomainException
{
    public function __construct()
    {
        parent::__construct('This branch is no longer active. Please log in again.');
    }

    public function errorCode(): string
    {
        return 'BRANCH_INACTIVE';
    }

    public function status(): int
    {
        return 401;
    }
}
