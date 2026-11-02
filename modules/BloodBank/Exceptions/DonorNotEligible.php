<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class DonorNotEligible extends DomainException
{
    public function __construct()
    {
        parent::__construct('The donor has not been accepted at the serological investigation yet.');
    }

    public function errorCode(): string
    {
        return 'DONOR_NOT_ELIGIBLE';
    }
}
