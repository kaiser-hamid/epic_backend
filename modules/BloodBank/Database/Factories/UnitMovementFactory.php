<?php

namespace Modules\BloodBank\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BloodBank\Enums\UnitStatus;
use Modules\BloodBank\Models\Unit;
use Modules\BloodBank\Models\UnitMovement;
use Modules\Shared\Auth\Models\User;

/**
 * @extends Factory<UnitMovement>
 */
final class UnitMovementFactory extends Factory
{
    protected $model = UnitMovement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'to_status' => UnitStatus::Available,
            'performed_by' => User::factory(),
            'occurred_at' => now(),
        ];
    }
}
