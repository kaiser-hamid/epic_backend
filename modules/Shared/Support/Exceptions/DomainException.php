<?php

namespace Modules\Shared\Support\Exceptions;

use RuntimeException;

/**
 * Base for business-rule violations. Rendered centrally as {"message", "code"} with status(),
 * plus any extra keys from context().
 */
abstract class DomainException extends RuntimeException
{
    abstract public function errorCode(): string;

    public function status(): int
    {
        return 422;
    }

    /**
     * Extra machine-readable fields merged into the error response.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [];
    }
}
