<?php

namespace Modules\BloodBank\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\BloodBank\Enums\StorageLocationType;
use Modules\BloodBank\Models\ComponentType;

/**
 * Component types with the shelf lives of the Blood Bank expiry table (PCV/WB 35 days, FFP/FP and cryo 364 days,
 * platelets and platelet apheresis 5 days). Which catalog items bill them is `BillableItemSeeder`. Safe to re-run:
 * rows are matched by code.
 */
final class ComponentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $fridge = StorageLocationType::Refrigerator;
        $freezer = StorageLocationType::Freezer;
        $agitator = StorageLocationType::PlateletAgitator;
        $cold = '2–6 °C';
        $frozen = '-18 °C or colder';
        $agitated = '20–24 °C, agitated';

        $componentTypes = [
            ['WB', 'BH', 'Whole Blood', 35, $cold, $fridge, true],
            ['PCV', 'BC', 'Packed Cell Volume', 35, $cold, $fridge, false],
            ['FP', 'BP', 'Fresh Plasma', 364, $frozen, $freezer, false],
            ['FFP', 'BF', 'Fresh Frozen Plasma', 364, $frozen, $freezer, false],
            ['CRYO', 'BR', 'Cryoprecipitate', 364, $frozen, $freezer, false],
            ['PLASMA', 'BS', 'Cryo Poor Plasma', 364, $frozen, $freezer, false],
            ['PLATELET', 'BT', 'Platelet Concentrate', 5, $agitated, $agitator, false],
            ['PRP', 'BQ', 'Platelet Rich Plasma', 5, $agitated, $agitator, false],
            ['PA', 'BA', 'Platelet Apheresis', 5, $agitated, $agitator, true],
        ];

        foreach ($componentTypes as [$code, $prefix, $name, $shelfLifeDays, $storageCondition, $locationType, $isWholeBag]) {
            ComponentType::query()->updateOrCreate(['code' => $code], [
                'unit_prefix' => $prefix,
                'name' => $name,
                'shelf_life_hours' => $shelfLifeDays * 24,
                'storage_condition' => $storageCondition,
                'default_location_type' => $locationType,
                'is_whole_bag' => $isWholeBag,
                'is_active' => true,
            ]);
        }
    }
}
