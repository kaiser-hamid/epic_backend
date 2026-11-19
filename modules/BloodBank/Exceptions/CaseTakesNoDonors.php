<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class CaseTakesNoDonors extends DomainException
{
    public function __construct()
    {
        parent::__construct('This case is a procedure (Autologous PRP or Therapeutic Phlebotomy); it takes no donors.');
    }

    public function errorCode(): string
    {
        return 'CASE_TAKES_NO_DONORS';
    }
}
