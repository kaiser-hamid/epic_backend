<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class CaseTakesNoBlood extends DomainException
{
    public function __construct()
    {
        parent::__construct('This case is a procedure (Autologous PRP or Therapeutic Phlebotomy); no blood is issued to it.');
    }

    public function errorCode(): string
    {
        return 'CASE_TAKES_NO_BLOOD';
    }
}
