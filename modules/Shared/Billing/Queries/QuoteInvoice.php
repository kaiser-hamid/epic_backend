<?php

namespace Modules\Shared\Billing\Queries;

use Modules\Shared\Billing\Data\PricedInvoice;
use Modules\Shared\Billing\Data\QuoteInvoiceData;
use Modules\Shared\Billing\Exceptions\DiscountPolicyNotAllowed;
use Modules\Shared\Billing\Exceptions\InvoiceCategoryMixed;
use Modules\Shared\Billing\Services\InvoicePricer;

/**
 * Prices a bill without saving it, with the same rules as saving, so the form never computes money itself.
 */
final class QuoteInvoice
{
    public function __construct(private readonly InvoicePricer $pricer) {}

    /**
     * @throws InvoiceCategoryMixed
     * @throws DiscountPolicyNotAllowed
     */
    public function get(QuoteInvoiceData $data): PricedInvoice
    {
        return $this->pricer->price($data->items, $data->patientTypeId, $data->discountPolicyId);
    }
}
