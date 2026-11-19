<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class NotAPhlebotomyCase extends DomainException
{
    public function __construct()
    {
        parent::__construct('This case is not a Therapeutic Phlebotomy.');
    }

    public function errorCode(): string
    {
        return 'NOT_A_PHLEBOTOMY_CASE';
    }
}
