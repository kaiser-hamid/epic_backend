<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class CloseReasonRequired extends DomainException
{
    public function __construct()
    {
        parent::__construct('Billed work is still outstanding on this case; give a reason to close it.');
    }

    public function errorCode(): string
    {
        return 'CLOSE_REASON_REQUIRED';
    }
}
