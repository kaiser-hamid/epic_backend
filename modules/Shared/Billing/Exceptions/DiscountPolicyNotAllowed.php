<?php

namespace Modules\Shared\Billing\Exceptions;

use Modules\Shared\Support\Exceptions\DomainException;

/**
 * The chosen discount policy is inactive or does not belong to the bill's patient type.
 */
final class DiscountPolicyNotAllowed extends DomainException
{
    public function __construct()
    {
        parent::__construct('This discount is not available for the selected patient type.');
    }

    public function errorCode(): string
    {
        return 'DISCOUNT_POLICY_NOT_ALLOWED';
    }
}
