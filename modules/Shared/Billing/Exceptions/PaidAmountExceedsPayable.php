<?php

namespace Modules\Shared\Billing\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class PaidAmountExceedsPayable extends DomainException
{
    public function __construct()
    {
        parent::__construct('The paid amount cannot be more than the payable amount.');
    }

    public function errorCode(): string
    {
        return 'PAID_AMOUNT_EXCEEDS_PAYABLE';
    }
}
