<?php

namespace Modules\Shared\Auth\Database\Seeders;

use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;
use Modules\Shared\Auth\Models\User;

/**
 * 20 demo staff accounts: `EMP-0002` … `EMP-0021`, usernames `staff01` … `staff20`, password `password`.
 * The last two are inactive. No branch permissions are granted. Skipped when non-admin users exist.
 */
final class UserSeeder extends Seeder
{
    private const COUNT = 20;

    public function run(): void
    {
        if (User::query()->where('is_super_admin', false)->exists()) {
            return;
        }

        $designations = ['Receptionist', 'Cashier', 'Lab Technician', 'Medical Technologist', 'Blood Bank Technician', 'Phlebotomist', 'Pathologist', 'Medical Officer', 'Nurse', 'Lab Manager'];

        User::factory()
            ->count(self::COUNT)
            ->state(new Sequence(fn (Sequence $sequence): array => [
                'employee_id' => sprintf('EMP-%04d', $sequence->index + 2),
                'username' => sprintf('staff%02d', $sequence->index + 1),
                'designation' => $designations[$sequence->index % count($designations)],
                'is_active' => $sequence->index < self::COUNT - 2,
            ]))
            ->create();
    }
}
