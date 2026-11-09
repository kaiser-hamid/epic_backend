<?php

namespace Modules\Shared\Billing\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

/**
 * A bill holds items of one category only: a Blood Bank bill has Blood Bank items only, and a general bill none.
 */
final class InvoiceCategoryMixed extends DomainException
{
    public function __construct()
    {
        parent::__construct('A bill cannot mix Blood Bank items with other items.');
    }

    public function errorCode(): string
    {
        return 'INVOICE_CATEGORY_MIXED';
    }
}
