<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\BloodBank\Database\Seeders\ComponentTypeSeeder;
use Modules\BloodBank\Database\Seeders\DonorSeeder;
use Modules\BloodBank\Database\Seeders\StorageLocationSeeder;
use Modules\Shared\Auth\Database\Seeders\UserSeeder;
use Modules\Shared\Doctor\Database\Seeders\DoctorSeeder;
use Modules\Shared\Patient\Database\Seeders\PatientSeeder;
use Modules\Shared\TestCatalog\Database\Seeders\DepartmentSeeder;
use Modules\Shared\TestCatalog\Database\Seeders\TestItemSeeder;

/**
 * Dummy data for local development; never run in production. Needs at least one user (run `DatabaseSeeder`
 * first) and is safe to re-run. `php artisan db:seed --class=DemoDataSeeder`
 */
class DemoDataSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            DepartmentSeeder::class,
            TestItemSeeder::class,
            DoctorSeeder::class,
            PatientSeeder::class,
            ComponentTypeSeeder::class,
            StorageLocationSeeder::class,
            DonorSeeder::class,
        ]);
    }
}
