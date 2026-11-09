<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

/**
 * No bill has the invoice number given for a donor.
 */
final class InvoiceNotFound extends DomainException
{
    public function __construct()
    {
        parent::__construct('No bill has this invoice number.');
    }

    public function errorCode(): string
    {
        return 'INVOICE_NOT_FOUND';
    }
}
