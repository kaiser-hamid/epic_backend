<?php

namespace Modules\Shared\Support\Exceptions;

use RuntimeException;

/**
 * Base for business-rule violations. Rendered centrally as {"message", "code"} with status().
 */
abstract class DomainException extends RuntimeException
{
    abstract public function errorCode(): string;

    public function status(): int
    {
        return 422;
    }
}
