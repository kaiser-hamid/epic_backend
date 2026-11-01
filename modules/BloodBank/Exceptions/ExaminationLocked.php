<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class ExaminationLocked extends DomainException
{
    public function __construct()
    {
        parent::__construct('The donor sample has been drawn; the physical examination can no longer be changed.');
    }

    public function errorCode(): string
    {
        return 'EXAMINATION_LOCKED';
    }

    public function status(): int
    {
        return 409;
    }
}
