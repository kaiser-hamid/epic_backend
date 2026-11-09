<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

/**
 * A donor's bill must be issued by the case's branch.
 */
final class InvoiceNotInBranch extends DomainException
{
    public function __construct()
    {
        parent::__construct('This bill was issued by another branch.');
    }

    public function errorCode(): string
    {
        return 'INVOICE_NOT_IN_BRANCH';
    }
}
