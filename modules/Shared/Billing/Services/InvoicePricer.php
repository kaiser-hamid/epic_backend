<?php

namespace Modules\Shared\Billing\Services;

use Modules\Shared\Billing\Data\BillLineInput;
use Modules\Shared\Billing\Data\InvoiceItemData;
use Modules\Shared\Billing\Data\PricedInvoice;
use Modules\Shared\Billing\Exceptions\DiscountPolicyNotAllowed;
use Modules\Shared\Billing\Exceptions\InvoiceCategoryMixed;
use Modules\Shared\Billing\Models\DiscountPolicy;
use Modules\Shared\Billing\Models\PatientType;
use Modules\Shared\TestCatalog\Enums\TestItemCategory;
use Modules\Shared\TestCatalog\Models\TestItem;

/**
 * Prices a bill from the catalog, for both the live preview and the saved bill, so they always agree: snapshots
 * each item's rate and discount override, checks the items share one category and the policy belongs to the
 * patient type, then runs the calculator. Items are expected to exist and be active (validated on the request).
 */
final class InvoicePricer
{
    public function __construct(private readonly InvoiceCalculator $calculator) {}

    /**
     * @param  list<InvoiceItemData>  $items
     *
     * @throws InvoiceCategoryMixed
     * @throws DiscountPolicyNotAllowed
     */
    public function price(array $items, int $patientTypeId, ?int $discountPolicyId): PricedInvoice
    {
        $testItems = TestItem::query()->findMany(array_column($items, 'testItemId'))->keyBy('id');
        $category = $this->category($testItems->pluck('category')->unique()->values()->all());

        $patientType = PatientType::query()->findOrFail($patientTypeId);
        $discountPolicy = $discountPolicyId === null ? null : $this->discountPolicy($patientType, $discountPolicyId);

        $lines = array_map(fn (InvoiceItemData $item): BillLineInput => new BillLineInput(
            testItemId: $item->testItemId,
            quantity: $item->quantity,
            rate: $testItems[$item->testItemId]->rate,
            itemDiscountRateBp: $testItems[$item->testItemId]->discount_rate_bp,
        ), $items);

        return new PricedInvoice(
            category: $category,
            patientType: $patientType,
            discountPolicy: $discountPolicy,
            totals: $this->calculator->calculate($lines, $discountPolicy?->rate_bp),
        );
    }

    /**
     * @param  list<TestItemCategory>  $categories
     *
     * @throws InvoiceCategoryMixed
     */
    private function category(array $categories): TestItemCategory
    {
        if (count($categories) !== 1) {
            throw new InvoiceCategoryMixed;
        }

        return $categories[0];
    }

    /**
     * @throws DiscountPolicyNotAllowed
     */
    private function discountPolicy(PatientType $patientType, int $discountPolicyId): DiscountPolicy
    {
        return $patientType->discountPolicies()
            ->where('sh_discount_policies.is_active', true)
            ->find($discountPolicyId)
            ?? throw new DiscountPolicyNotAllowed;
    }
}
