<?php

namespace Modules\Shared\Billing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Shared\Billing\Data\PricedInvoice;
use Modules\Shared\Billing\Data\PricedLine;

/**
 * Server-computed totals of a bill being filled in, in paisa: `subtotal_amount` − `discount_amount` +
 * `rounding_amount` = `amount` (payable).
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
            'category' => $this->resource->category->value,
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
