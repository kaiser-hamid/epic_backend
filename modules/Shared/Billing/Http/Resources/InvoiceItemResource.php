<?php

namespace Modules\Shared\Billing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Shared\Billing\Models\BillingItem;

/**
 * A bill line. Code, name and department come from the catalog; rate and amount are the billed snapshot.
 *
 * @mixin BillingItem
 */
final class InvoiceItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'test_item_id' => $this->test_item_id,
            'code' => $this->whenLoaded('testItem', fn () => $this->testItem->code),
            'name' => $this->whenLoaded('testItem', fn () => $this->testItem->name),
            'department_name' => $this->whenLoaded('testItem', fn () => $this->testItem->department_name),
            'quantity' => $this->quantity,
            'rate' => $this->rate,
            'amount' => $this->amount,
        ];
    }
}
