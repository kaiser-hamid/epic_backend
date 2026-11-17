<?php

namespace Modules\Shared\Billing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Shared\Billing\Data\PricedInvoice;
use Modules\Shared\Billing\Data\PricedLine;
use Modules\Shared\TestCatalog\Enums\TestItemCategory;

/**
 * Server-computed totals of a bill being filled in, in paisa: `subtotal_amount` − `discount_amount` +
 * `rounding_amount` = `amount` (payable). `categories` lists the distinct item categories on the bill; with
 * `blood_bank` among them the bill needs the Blood Bank `category_details` ("Care of").
 *
 * @property PricedInvoice $resource
 */
final class InvoiceQuoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $totals = $this->resource->totals;

        return [
            'categories' => array_map(fn (TestItemCategory $category): string => $category->value, $this->resource->categories()),
            'discount_policy' => $this->resource->discountPolicy === null ? null : [
                'id' => $this->resource->discountPolicy->id,
                'name' => $this->resource->discountPolicy->name,
                'discount_percent' => $this->resource->discountPolicy->rate_bp / 100,
            ],
            'subtotal_amount' => $totals->subtotalAmount,
            'discount_amount' => $totals->discountAmount,
            'rounding_amount' => $totals->roundingAmount,
            'amount' => $totals->amount,
            'items' => array_map(fn (PricedLine $line): array => [
                'test_item_id' => $line->testItemId,
                'category' => $this->resource->categoryOf($line->testItemId)->value,
                'quantity' => $line->quantity,
                'rate' => $line->rate,
                'amount' => $line->amount,
                'discount_percent' => $line->discountRateBp === null ? null : $line->discountRateBp / 100,
                'discount_amount' => $line->discountAmount,
                'net_amount' => $line->netAmount,
            ], $totals->lines),
        ];
    }
}
