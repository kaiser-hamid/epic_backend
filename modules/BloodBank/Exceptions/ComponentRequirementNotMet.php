<?php

namespace Modules\BloodBank\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

/**
 * The preparation does not give the patient what the case was billed for: whole blood when the case needs whole
 * blood, otherwise one unit of every component the case still needs.
 */
final class ComponentRequirementNotMet extends DomainException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return 'COMPONENT_REQUIREMENT_NOT_MET';
    }
}
