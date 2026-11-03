<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class CrossmatchNotRecorded extends DomainException
{
    public function __construct()
    {
        parent::__construct('The unit has no crossmatch, so there is no Compatibility Test Report to print.');
    }

    public function errorCode(): string
    {
        return 'CROSSMATCH_NOT_RECORDED';
    }
}
