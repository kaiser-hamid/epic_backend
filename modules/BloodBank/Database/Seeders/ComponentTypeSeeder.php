<?php

namespace Modules\BloodBank\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\BloodBank\Models\ComponentType;
use Modules\Shared\TestCatalog\Enums\TestItemCategory;
use Modules\Shared\TestCatalog\Models\TestItem;
use RuntimeException;

/**
 * Component types with the shelf lives from the Blood Bank price sheet. Each type is created from the Blood Bank
 * catalog item that bills it, found by item code (`BloodBankTestItemSeeder` must run first), copying the item's
 * code and name. Safe to re-run: rows are matched by code.
 */
final class ComponentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $componentTypes = [
            ['item' => 'WB01', 'code' => 'WB', 'unit_prefix' => 'BH', 'name' => 'Whole Blood', 'shelf_life_hours' => 35 * 24, 'storage_condition' => '2–6 °C'],
            ['item' => 'BBT03', 'code' => 'PCV', 'unit_prefix' => 'BC', 'name' => 'Packed Cell Volume', 'shelf_life_hours' => 35 * 24, 'storage_condition' => '2–6 °C'],
            ['item' => 'FP001', 'code' => 'FP', 'unit_prefix' => 'BP', 'name' => 'Fresh Plasma', 'shelf_life_hours' => 364 * 24, 'storage_condition' => '-18 °C or colder'],
            ['item' => 'CP01', 'code' => 'CRYO', 'unit_prefix' => 'BR', 'name' => 'Cryoprecipitate', 'shelf_life_hours' => 364 * 24, 'storage_condition' => '-18 °C or colder'],
            ['item' => 'PC001', 'code' => 'PLATELET', 'unit_prefix' => 'BT', 'name' => 'Platelet Concentrate', 'shelf_life_hours' => 5 * 24, 'storage_condition' => '20–24 °C, agitated'],
            ['item' => 'CPP01', 'code' => 'PLASMA', 'unit_prefix' => 'BS', 'name' => 'Plasma', 'shelf_life_hours' => 364 * 24, 'storage_condition' => '-18 °C or colder'],
        ];

        foreach ($componentTypes as $componentType) {
            $itemCode = $componentType['item'];
            unset($componentType['item']);

            $item = TestItem::query()
                ->where('category', TestItemCategory::BloodBank)
                ->where('is_active', true)
                ->where('code', $itemCode)
                ->first()
                ?? throw new RuntimeException("Blood Bank test item {$itemCode} is missing; run BloodBankTestItemSeeder first.");

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
