<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\BloodBank\Database\Seeders\ComponentTypeSeeder;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Rbac\Actions\SeedRoleTemplates;
use Modules\Shared\Rbac\Actions\SyncPermissionCatalog;
use Modules\Shared\TestCatalog\Database\Seeders\DepartmentSeeder;
use Modules\Shared\TestCatalog\Database\Seeders\TestItemSeeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(SyncPermissionCatalog $syncPermissionCatalog, SeedRoleTemplates $seedRoleTemplates): void
    {
        $syncPermissionCatalog->handle();
        $seedRoleTemplates->handle();

        // The branch code is also the prefix of its invoice numbers (e.g. NE2611120001).
        foreach (['NE' => 'East Gate', 'NM' => 'Medical Main Gate', 'NA' => 'Anderkilla', 'NH' => 'Halishahar', 'NG' => 'Golden Core'] as $code => $name) {
            Branch::query()->firstOrCreate(['code' => $code], ['name' => $name, 'is_active' => true]);
        }

        $this->call([DepartmentSeeder::class, TestItemSeeder::class, ComponentTypeSeeder::class]);

        User::factory()->superAdmin()->mustChangePassword()->create([
            'employee_id' => 'EMP-0001',
            'name' => 'Test User',
            'username' => 'khtusher',
            'password' => Hash::make('Pa$$word'),
        ]);
    }
}
