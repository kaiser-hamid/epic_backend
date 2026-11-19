<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class ServicesNotCombinable extends DomainException
{
    public function __construct()
    {
        parent::__construct('A bill may carry one Blood Bank service: blood components, Autologous PRP or Therapeutic Phlebotomy, not several.');
    }

    public function errorCode(): string
    {
        return 'SERVICES_NOT_COMBINABLE';
    }
}
