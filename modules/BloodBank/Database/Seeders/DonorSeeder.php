<?php

namespace Modules\BloodBank\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\BloodBank\Models\Donor;
use Modules\Shared\Auth\Models\User;

/**
 * 100 demo donors, about a third of them regular volunteers. Skipped when donors exist.
 */
final class DonorSeeder extends Seeder
{
    private const COUNT = 100;

    public function run(): void
    {
        if (Donor::query()->exists()) {
            return;
        }

        Donor::factory()
            ->count(self::COUNT)
            ->state(fn (): array => ['is_regular_volunteer' => fake()->boolean(33)])
            ->create(['created_by' => User::query()->orderBy('id')->value('id')]);
    }
}
