<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class SampleAlreadyCollected extends DomainException
{
    public function __construct()
    {
        parent::__construct('A sample has already been collected for this patient or donor.');
    }

    public function errorCode(): string
    {
        return 'SAMPLE_ALREADY_COLLECTED';
    }

    public function status(): int
    {
        return 409;
    }
}
