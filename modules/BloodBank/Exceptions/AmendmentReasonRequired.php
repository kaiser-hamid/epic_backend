<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class AmendmentReasonRequired extends DomainException
{
    public function __construct()
    {
        parent::__construct('Changing a saved result needs an amendment reason.');
    }

    public function errorCode(): string
    {
        return 'AMENDMENT_REASON_REQUIRED';
    }
}
