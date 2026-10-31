<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class CaseDonorNotInCase extends DomainException
{
    public function __construct()
    {
        parent::__construct('The donor is not registered for this case.');
    }

    public function errorCode(): string
    {
        return 'CASE_DONOR_NOT_IN_CASE';
    }
}
