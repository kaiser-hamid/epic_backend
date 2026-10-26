<?php

namespace Modules\BloodBank\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BloodBank\Enums\ConsumableTransactionType;
use Modules\BloodBank\Models\Consumable;
use Modules\BloodBank\Models\ConsumableTransaction;
use Modules\Shared\Auth\Models\User;

/**
 * @extends Factory<ConsumableTransaction>
 */
final class ConsumableTransactionFactory extends Factory
{
    protected $model = ConsumableTransaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'consumable_id' => Consumable::factory(),
            'type' => ConsumableTransactionType::Received,
            'quantity' => 10,
            'balance_after' => 10,
            'performed_by' => User::factory(),
            'occurred_at' => now(),
        ];
    }
}
