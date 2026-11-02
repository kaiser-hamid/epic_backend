<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class BagAlreadyCollected extends DomainException
{
    public function __construct()
    {
        parent::__construct('Blood has already been collected from this donor for this case.');
    }

    public function errorCode(): string
    {
        return 'BAG_ALREADY_COLLECTED';
    }

    public function status(): int
    {
        return 409;
    }
}
