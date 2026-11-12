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
        // Optional modules per branch: only East Gate runs a blood bank for now.
        $branches = [
            'NE' => ['East Gate', ['blood-bank']],
            'NM' => ['Medical Main Gate', []],
            'NA' => ['Anderkilla', []],
            'NH' => ['Halishahar', []],
            'NG' => ['Golden Core', []],
        ];

        foreach ($branches as $code => [$name, $modules]) {
            $branch = Branch::query()->firstOrCreate(['code' => $code], ['name' => $name, 'is_active' => true]);

            foreach ($modules as $module) {
                BranchModule::query()->firstOrCreate(['branch_id' => $branch->id, 'module' => $module]);
            }
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
