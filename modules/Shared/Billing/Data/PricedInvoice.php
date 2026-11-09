<?php

namespace Modules\Shared\Billing\Data;

use Modules\Shared\Billing\Models\DiscountPolicy;
use Modules\Shared\Billing\Models\PatientType;
use Modules\Shared\TestCatalog\Enums\TestItemCategory;

/**
 * A bill priced but not saved: the single category of its items, the patient type and discount policy it uses,
 * and the computed totals.
 */
final readonly class PricedInvoice
{
    public function __construct(
        public TestItemCategory $category,
        public PatientType $patientType,
        public ?DiscountPolicy $discountPolicy,
        public InvoiceTotals $totals,
    ) {}
}
