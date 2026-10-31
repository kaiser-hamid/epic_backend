<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class CaseNotFound extends DomainException
{
    public function __construct()
    {
        parent::__construct('No Blood Bank case of this branch was found for this number.');
    }

    public function errorCode(): string
    {
        return 'CASE_NOT_FOUND';
    }

    public function status(): int
    {
        return 404;
    }
}
