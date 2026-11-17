<?php

namespace Modules\Shared\Billing\Data;

use Modules\Shared\Billing\Models\DiscountPolicy;
use Modules\Shared\Billing\Models\PatientType;
use Modules\Shared\TestCatalog\Enums\TestItemCategory;

/**
 * A bill priced but not saved: the category of each billed item, the patient type and discount policy it uses,
 * and the computed totals. A bill may mix categories.
 */
final readonly class PricedInvoice
{
    /**
     * @param  array<int, TestItemCategory>  $itemCategories  test item id => category, in bill order
     */
    public function __construct(
        public array $itemCategories,
        public PatientType $patientType,
        public ?DiscountPolicy $discountPolicy,
        public InvoiceTotals $totals,
    ) {}

    public function categoryOf(int $testItemId): TestItemCategory
    {
        return $this->itemCategories[$testItemId];
    }

    /**
     * The distinct categories on the bill, in the order they first appear.
     *
     * @return list<TestItemCategory>
     */
    public function categories(): array
    {
        $categories = [];

        foreach ($this->itemCategories as $category) {
            $categories[$category->value] = $category;
        }

        return array_values($categories);
    }
}
