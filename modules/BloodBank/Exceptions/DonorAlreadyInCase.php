<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class DonorAlreadyInCase extends DomainException
{
    public function __construct()
    {
        parent::__construct('This donor is already on the case.');
    }

    public function errorCode(): string
    {
        return 'DONOR_ALREADY_IN_CASE';
    }
}
