<?php

namespace Modules\Shared\Auth\Exceptions;

use Carbon\CarbonInterface;
use Modules\Shared\Support\Exceptions\DomainException;

final class AccountLocked extends DomainException
{
    public function __construct(private readonly CarbonInterface $lockedUntil)
    {
        parent::__construct('Too many failed attempts. This account is temporarily locked.');
    }

    public function errorCode(): string
    {
        return 'ACCOUNT_LOCKED';
    }

    public function status(): int
    {
        return 423;
    }

    public function context(): array
    {
        return ['locked_until' => $this->lockedUntil->toIso8601String()];
    }
}
