<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class CrossmatchIncompatible extends DomainException
{
    public function __construct()
    {
        parent::__construct('An incompatible unit cannot be issued; both major and minor crossmatch must be compatible.');
    }

    public function errorCode(): string
    {
        return 'CROSSMATCH_INCOMPATIBLE';
    }
}
