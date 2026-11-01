<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class DonorNotFit extends DomainException
{
    public function __construct()
    {
        parent::__construct('The physician has not accepted this donor at the physical examination yet.');
    }

    public function errorCode(): string
    {
        return 'DONOR_NOT_FIT';
    }
}
