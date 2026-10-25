<?php

namespace Modules\BloodBank\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BloodBank\Enums\CollectionStatus;
use Modules\BloodBank\Models\CaseDonor;
use Modules\BloodBank\Models\Collection;

/**
 * @extends Factory<Collection>
 */
final class CollectionFactory extends Factory
{
    protected $model = Collection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bag_no' => fake()->unique()->numerify('BB-B-######'),
            'case_donor_id' => CaseDonor::factory(),
            'volume_ml' => 450,
            'collected_at' => now(),
            'collected_by' => User::factory(),
            'status' => CollectionStatus::Collected,
        ];
    }
}
