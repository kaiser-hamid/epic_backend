<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

/**
 * A donor's bill must not be cancelled.
 */
final class InvoiceNotActive extends DomainException
{
    public function __construct()
    {
        parent::__construct('This bill is cancelled.');
    }

    public function errorCode(): string
    {
        return 'INVOICE_NOT_ACTIVE';
    }
}
