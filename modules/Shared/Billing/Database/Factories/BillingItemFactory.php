<?php

namespace Modules\Shared\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Shared\Billing\Models\BillingItem;
use Modules\Shared\TestCatalog\Database\Factories\TestItemFactory;
use Modules\Shared\TestCatalog\Enums\TestItemCategory;
use Modules\Shared\TestCatalog\Models\TestItem;

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
            'category' => fn (array $attributes): TestItemCategory => TestItem::query()->findOrFail($attributes['test_item_id'])->category,
            'amount' => 50000,
            'net_amount' => 50000,
        ];
    }
}
