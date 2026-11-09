<?php

namespace Modules\BloodBank\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\BloodBank\Models\ComponentType;
use Modules\Shared\TestCatalog\Enums\TestItemCategory;
use Modules\Shared\TestCatalog\Models\TestItem;
use RuntimeException;

/**
 * Placeholder component types until the department confirms terminology and shelf lives (plan open decision 3).
 * Each type is created from the Blood Bank catalog item that bills it (`TestItemSeeder` must run first), copying
 * the item's code and name. Safe to re-run: rows are matched by code.
 */
final class ComponentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $componentTypes = [
            ['item' => 'Whole Blood Processing', 'code' => 'WB', 'unit_prefix' => 'BH', 'name' => 'Whole Blood', 'shelf_life_hours' => 35 * 24, 'storage_condition' => '2–6 °C'],
            ['item' => 'Packed Red Cells', 'code' => 'PCV', 'unit_prefix' => 'BC', 'name' => 'Packed Cell Volume', 'shelf_life_hours' => 42 * 24, 'storage_condition' => '2–6 °C'],
            ['item' => 'Fresh Frozen Plasma', 'code' => 'FP', 'unit_prefix' => 'BP', 'name' => 'Fresh Plasma', 'shelf_life_hours' => 365 * 24, 'storage_condition' => '-18 °C or colder'],
            ['item' => 'Cryoprecipitate', 'code' => 'CRYO', 'unit_prefix' => 'BR', 'name' => 'Cryoprecipitate', 'shelf_life_hours' => 365 * 24, 'storage_condition' => '-18 °C or colder'],
            ['item' => 'Platelet Concentrate', 'code' => 'PLATELET', 'unit_prefix' => 'BT', 'name' => 'Platelet Concentrate', 'shelf_life_hours' => 5 * 24, 'storage_condition' => '20–24 °C, agitated'],
            ['item' => 'Plasma', 'code' => 'PLASMA', 'unit_prefix' => 'BS', 'name' => 'Plasma', 'shelf_life_hours' => 365 * 24, 'storage_condition' => '-18 °C or colder'],
        ];

        foreach ($componentTypes as $componentType) {
            $itemName = $componentType['item'];
            unset($componentType['item']);

            $item = TestItem::query()
                ->where('category', TestItemCategory::BloodBank)
                ->where('is_active', true)
                ->where('name', $itemName)
                ->first()
                ?? throw new RuntimeException("Blood Bank test item \"{$itemName}\" is missing; run TestItemSeeder first.");

            ComponentType::query()->updateOrCreate(['code' => $componentType['code']], [
                ...$componentType,
                'test_item_id' => $item->id,
                'test_item_code' => $item->code,
                'test_item_name' => $item->name,
                'is_active' => true,
            ]);
        }
    }
}
