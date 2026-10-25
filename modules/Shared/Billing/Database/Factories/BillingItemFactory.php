<?php

namespace Modules\Shared\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Shared\Billing\Models\BillingItem;
use Modules\Shared\TestCatalog\Database\Factories\TestItemFactory;

/**
 * @extends Factory<BillingItem>
 */
final class BillingItemFactory extends Factory
{
    protected $model = BillingItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'billing_id' => BillingFactory::new(),
            'test_item_id' => TestItemFactory::new(),
            'quantity' => 1,
            'rate' => 50000,
            'amount' => 50000,
        ];
    }
}
