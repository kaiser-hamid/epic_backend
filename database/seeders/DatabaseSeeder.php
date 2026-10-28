<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\BloodBank\Database\Seeders\ComponentTypeSeeder;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Rbac\Actions\SyncPermissionCatalog;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(SyncPermissionCatalog $syncPermissionCatalog): void
    {
        $syncPermissionCatalog->handle();

        Branch::factory()->create([
            'code' => 'NB',
            'name' => 'Blood Bank',
        ]);

        $this->call(ComponentTypeSeeder::class);

        User::factory()->superAdmin()->mustChangePassword()->create([
            'employee_id' => 'EMP-0001',
            'name' => 'Test User',
            'username' => 'khtusher',
            'password' => Hash::make('Pa$$word'),
        ]);
    }
}
