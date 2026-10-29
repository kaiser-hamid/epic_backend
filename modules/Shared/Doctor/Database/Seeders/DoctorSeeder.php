<?php

namespace Modules\Shared\Doctor\Database\Seeders;

use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Doctor\Models\Doctor;

/**
 * 500 demo referring doctors (`DR-0001` … `DR-0500`), about 1 in 20 inactive. Skipped when doctors exist.
 */
final class DoctorSeeder extends Seeder
{
    private const COUNT = 500;

    public function run(): void
    {
        if (Doctor::query()->exists()) {
            return;
        }

        $degrees = ['MBBS', 'MBBS, FCPS (Medicine)', 'MBBS, MD (Cardiology)', 'MBBS, MS (Surgery)', 'MBBS, FCPS (Gynae & Obs)', 'MBBS, DCH', 'MBBS, FRCS'];

        Doctor::factory()
            ->count(self::COUNT)
            ->state(new Sequence(fn (Sequence $sequence): array => [
                'code' => sprintf('DR-%04d', $sequence->index + 1),
                'degree' => fake()->randomElement($degrees),
                'is_active' => fake()->boolean(95),
            ]))
            ->create(['created_by' => User::query()->orderBy('id')->value('id')]);
    }
}
