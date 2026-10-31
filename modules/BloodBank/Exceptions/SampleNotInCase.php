<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class SampleNotInCase extends DomainException
{
    public function __construct()
    {
        parent::__construct('The sample does not belong to this case.');
    }

    public function errorCode(): string
    {
        return 'SAMPLE_NOT_IN_CASE';
    }
}
