<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class DonorSampleNotFound extends DomainException
{
    public function __construct()
    {
        parent::__construct('No donor sample of this branch was found for this number.');
    }

    public function errorCode(): string
    {
        return 'DONOR_SAMPLE_NOT_FOUND';
    }

    public function status(): int
    {
        return 404;
    }
}
