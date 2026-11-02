<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class CollectionNotInCase extends DomainException
{
    public function __construct()
    {
        parent::__construct('The blood bag does not belong to this case.');
    }

    public function errorCode(): string
    {
        return 'COLLECTION_NOT_IN_CASE';
    }
}
