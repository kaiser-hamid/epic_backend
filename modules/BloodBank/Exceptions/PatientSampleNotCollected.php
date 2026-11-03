<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class PatientSampleNotCollected extends DomainException
{
    public function __construct()
    {
        parent::__construct('The patient sample has not been collected.');
    }

    public function errorCode(): string
    {
        return 'PATIENT_SAMPLE_NOT_COLLECTED';
    }
}
