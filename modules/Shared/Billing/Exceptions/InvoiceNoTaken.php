<?php

namespace Modules\Shared\Billing\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class InvoiceNoTaken extends DomainException
{
    public function __construct()
    {
        parent::__construct('This invoice number is already used by another bill.');
    }

    public function errorCode(): string
    {
        return 'INVOICE_NO_TAKEN';
    }
}
