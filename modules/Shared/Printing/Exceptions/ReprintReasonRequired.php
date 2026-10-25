<?php

namespace Modules\Shared\Printing\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class ReprintReasonRequired extends DomainException
{
    public function __construct()
    {
        parent::__construct('A reason is required to reprint this document.');
    }

    public function errorCode(): string
    {
        return 'REPRINT_REASON_REQUIRED';
    }
}
