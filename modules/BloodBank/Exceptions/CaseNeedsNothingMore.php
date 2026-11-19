<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class CaseNeedsNothingMore extends DomainException
{
    public function __construct()
    {
        parent::__construct('The case already holds every component it was billed for; no more blood is collected for it.');
    }

    public function errorCode(): string
    {
        return 'CASE_NEEDS_NOTHING_MORE';
    }
}
