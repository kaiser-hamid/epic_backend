<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class BagCancelled extends DomainException
{
    public function __construct()
    {
        parent::__construct('This blood bag was cancelled and cannot be prepared.');
    }

    public function errorCode(): string
    {
        return 'BAG_CANCELLED';
    }
}
