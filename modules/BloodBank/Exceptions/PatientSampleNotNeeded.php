<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class PatientSampleNotNeeded extends DomainException
{
    public function __construct()
    {
        parent::__construct('A Therapeutic Phlebotomy takes no sample.');
    }

    public function errorCode(): string
    {
        return 'PATIENT_SAMPLE_NOT_NEEDED';
    }
}
