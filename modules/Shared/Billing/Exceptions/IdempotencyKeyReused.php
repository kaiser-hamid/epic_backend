<?php

namespace Modules\Shared\Billing\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

/**
 * Another user's bill was already saved under this idempotency key. A replay by the same user returns that bill
 * instead.
 */
final class IdempotencyKeyReused extends DomainException
{
    public function __construct()
    {
        parent::__construct('This form was already submitted by another user; reload it to start a new bill.');
    }

    public function errorCode(): string
    {
        return 'IDEMPOTENCY_KEY_REUSED';
    }

    public function status(): int
    {
        return 409;
    }
}
