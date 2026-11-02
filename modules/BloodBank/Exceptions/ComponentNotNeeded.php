<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

final class ComponentNotNeeded extends DomainException
{
    public function __construct()
    {
        parent::__construct('The case did not request this component, or already has every unit it requested.');
    }

    public function errorCode(): string
    {
        return 'COMPONENT_NOT_NEEDED';
    }
}
