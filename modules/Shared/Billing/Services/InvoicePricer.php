<?php

namespace Modules\Shared\Billing\Services;

use Modules\Shared\Billing\Data\BillLineInput;
use Modules\Shared\Billing\Data\InvoiceItemData;
use Modules\Shared\Billing\Data\PricedInvoice;
use Modules\Shared\Billing\Exceptions\DiscountPolicyNotAllowed;
use Modules\Shared\Billing\Models\DiscountPolicy;
use Modules\Shared\Billing\Models\PatientType;
use Modules\Shared\TestCatalog\Models\TestItem;

/**
 * Prices a bill from the catalog, for both the live preview and the saved bill, so they always agree: snapshots
 * each item's rate, discount override and category, checks the policy belongs to the patient type, then runs the
 * calculator. Items may be of any category. Items are expected to exist and be active (validated on the request).
 */
final class InvoicePricer
{
    public function __construct(private readonly InvoiceCalculator $calculator) {}

    /**
     * @param  list<InvoiceItemData>  $items
     *
     * @throws DiscountPolicyNotAllowed
     */
    public function price(array $items, int $patientTypeId, ?int $discountPolicyId): PricedInvoice
    {
        $testItems = TestItem::query()->findMany(array_column($items, 'testItemId'))->keyBy('id');

        $patientType = PatientType::query()->findOrFail($patientTypeId);
        $discountPolicy = $discountPolicyId === null ? null : $this->discountPolicy($patientType, $discountPolicyId);

        $lines = array_map(fn (InvoiceItemData $item): BillLineInput => new BillLineInput(
            testItemId: $item->testItemId,
            quantity: $item->quantity,
            rate: $testItems[$item->testItemId]->rate,
            itemDiscountRateBp: $testItems[$item->testItemId]->discount_rate_bp,
        ), $items);

        $itemCategories = [];

        foreach ($items as $item) {
            $itemCategories[$item->testItemId] = $testItems[$item->testItemId]->category;
        }

        return new PricedInvoice(
            itemCategories: $itemCategories,
            patientType: $patientType,
            discountPolicy: $discountPolicy,
            totals: $this->calculator->calculate($lines, $discountPolicy?->rate_bp),
        );
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
