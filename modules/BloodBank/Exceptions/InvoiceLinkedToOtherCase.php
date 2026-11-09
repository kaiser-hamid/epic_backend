<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

/**
 * A bill belongs to one case only: the case it opened, or the case whose donors it was attached to.
 */
final class InvoiceLinkedToOtherCase extends DomainException
{
    public function __construct()
    {
        parent::__construct('This bill already belongs to another case.');
    }

    public function errorCode(): string
    {
        return 'INVOICE_LINKED_TO_OTHER_CASE';
    }
}
