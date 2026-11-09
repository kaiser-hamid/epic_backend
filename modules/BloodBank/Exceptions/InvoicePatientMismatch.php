<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

/**
 * A donor's bill must be billed to the case's patient.
 */
final class InvoicePatientMismatch extends DomainException
{
    public function __construct()
    {
        parent::__construct('This bill belongs to another patient.');
    }

    public function errorCode(): string
    {
        return 'INVOICE_PATIENT_MISMATCH';
    }
}
