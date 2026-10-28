<?php

namespace Modules\BloodBank\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\BloodBank\Models\ComponentType;

/**
 * Placeholder component types until the department confirms terminology and shelf lives (plan open decision 3).
 * Safe to re-run: rows are matched by code.
 */
final class ComponentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $componentTypes = [
            ['code' => 'WB', 'name' => 'Whole Blood', 'shelf_life_hours' => 35 * 24, 'storage_condition' => '2–6 °C'],
            ['code' => 'PCV', 'name' => 'Packed Cell Volume', 'shelf_life_hours' => 42 * 24, 'storage_condition' => '2–6 °C'],
            ['code' => 'FP', 'name' => 'Fresh Plasma', 'shelf_life_hours' => 365 * 24, 'storage_condition' => '-18 °C or colder'],
            ['code' => 'CRYO', 'name' => 'Cryoprecipitate', 'shelf_life_hours' => 365 * 24, 'storage_condition' => '-18 °C or colder'],
            ['code' => 'PLATELET', 'name' => 'Platelet Concentrate', 'shelf_life_hours' => 5 * 24, 'storage_condition' => '20–24 °C, agitated'],
            ['code' => 'PLASMA', 'name' => 'Plasma', 'shelf_life_hours' => 365 * 24, 'storage_condition' => '-18 °C or colder'],
        ];

        foreach ($componentTypes as $componentType) {
            ComponentType::query()->updateOrCreate(['code' => $componentType['code']], [...$componentType, 'is_active' => true]);
        }
    }
}
