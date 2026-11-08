<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class ReactiveDonorNotAcceptable extends DomainException
{
    public function __construct()
    {
        parent::__construct('A donor with a reactive (or malaria found) screening result cannot be accepted; defer the donor.');
    }

    public function errorCode(): string
    {
        return 'REACTIVE_DONOR_NOT_ACCEPTABLE';
    }
}
