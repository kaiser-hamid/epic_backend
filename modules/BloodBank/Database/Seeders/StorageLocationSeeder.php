<?php

namespace Modules\BloodBank\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\BloodBank\Enums\StorageLocationType;
use Modules\BloodBank\Models\StorageLocation;

/**
 * Blood bank fridges, freezers and the platelet agitator. Matched on `code`, so it is safe to re-run.
 */
final class StorageLocationSeeder extends Seeder
{
    public function run(): void
    {
        $locations = [
            ['code' => 'REF-01', 'name' => 'Blood Bank Refrigerator 1', 'type' => StorageLocationType::Refrigerator, 'min_temperature_c' => 2, 'max_temperature_c' => 6],
            ['code' => 'REF-02', 'name' => 'Blood Bank Refrigerator 2', 'type' => StorageLocationType::Refrigerator, 'min_temperature_c' => 2, 'max_temperature_c' => 6],
            ['code' => 'FRZ-01', 'name' => 'Plasma Freezer 1', 'type' => StorageLocationType::Freezer, 'min_temperature_c' => -40, 'max_temperature_c' => -18],
            ['code' => 'FRZ-02', 'name' => 'Plasma Freezer 2', 'type' => StorageLocationType::Freezer, 'min_temperature_c' => -40, 'max_temperature_c' => -18],
            ['code' => 'AGT-01', 'name' => 'Platelet Agitator', 'type' => StorageLocationType::PlateletAgitator, 'min_temperature_c' => 20, 'max_temperature_c' => 24],
            ['code' => 'QRN-01', 'name' => 'Quarantine Shelf', 'type' => StorageLocationType::Other, 'min_temperature_c' => null, 'max_temperature_c' => null],
        ];

        foreach ($locations as $location) {
            StorageLocation::query()->updateOrCreate(['code' => $location['code']], [...$location, 'is_active' => true]);
        }
    }
}
