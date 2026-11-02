<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class DonorSampleNotCollected extends DomainException
{
    public function __construct()
    {
        parent::__construct('The donor sample has not been collected yet.');
    }

    public function errorCode(): string
    {
        return 'DONOR_SAMPLE_NOT_COLLECTED';
    }
}
