<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class DonorDeferred extends DomainException
{
    public function __construct()
    {
        parent::__construct('The donor was deferred; register a replacement donor instead.');
    }

    public function errorCode(): string
    {
        return 'DONOR_DEFERRED';
    }
}
