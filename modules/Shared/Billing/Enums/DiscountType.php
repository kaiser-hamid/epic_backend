<?php

namespace Modules\Shared\Billing\Enums;

/**
 * How a discount policy's value is applied. Only percentages exist today; a fixed amount would be a new case.
 */
enum DiscountType: string
{
    case Percentage = 'percentage';
}
