<?php

namespace Modules\Shared\Billing\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Shared\Billing\Enums\DiscountType;

/**
 * The discount policy a bill applied, snapshotted (`name`, `type`, `rate_bp`) so later policy changes never alter
 * the bill. `amount` is the bill's total discount in paisa.
 */
#[Table('sh_billing_discounts')]
#[Fillable(['billing_id', 'discount_policy_id', 'name', 'type', 'rate_bp', 'amount'])]
final class BillingDiscount extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DiscountType::class,
        ];
    }

    public function billing(): BelongsTo
    {
        return $this->belongsTo(Billing::class, 'billing_id');
    }

    public function discountPolicy(): BelongsTo
    {
        return $this->belongsTo(DiscountPolicy::class, 'discount_policy_id');
    }
}
