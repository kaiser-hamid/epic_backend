<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class BagNotFound extends DomainException
{
    public function __construct()
    {
        parent::__construct('No blood bag with this number was collected in this branch.');
    }

    public function errorCode(): string
    {
        return 'BAG_NOT_FOUND';
    }

    public function status(): int
    {
        return 404;
    }
}
