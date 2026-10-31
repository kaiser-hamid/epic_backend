<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class CaseNotOpen extends DomainException
{
    public function __construct()
    {
        parent::__construct('The case is closed or cancelled.');
    }

    public function errorCode(): string
    {
        return 'CASE_NOT_OPEN';
    }
}
