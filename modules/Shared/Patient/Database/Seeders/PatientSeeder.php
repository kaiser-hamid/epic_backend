<?php

namespace Modules\Shared\Patient\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Patient\Enums\Gender;
use Modules\Shared\Patient\Enums\PatientTitle;
use Modules\Shared\Patient\Models\PatientMaster;

/**
 * 100 demo patients. Like a real registration, the date of birth is back-calculated from the typed age.
 * Skipped when patients exist.
 */
final class PatientSeeder extends Seeder
{
    private const COUNT = 100;

    public function run(): void
    {
        if (PatientMaster::query()->exists()) {
            return;
        }

        PatientMaster::factory()
            ->count(self::COUNT)
            ->state(function (array $attributes): array {
                $gender = $attributes['gender'];

                return [
                    'title' => $gender === Gender::Female ? PatientTitle::Mrs : PatientTitle::Mr,
                    'date_of_birth' => CarbonImmutable::today()
                        ->subYearsNoOverflow($attributes['age_years'])
                        ->subMonthsNoOverflow($attributes['age_months'])
                        ->subDays($attributes['age_days']),
                ];
            })
            ->create(['created_by' => User::query()->orderBy('id')->value('id')]);
    }
}
