<?php

namespace Modules\Shared\TestCatalog\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Shared\TestCatalog\Enums\TestItemCategory;
use Modules\Shared\TestCatalog\Models\Department;
use Modules\Shared\TestCatalog\Models\TestItem;
use RuntimeException;

/**
 * The Blood Bank price list (`docs/blood-bank-items.png`), every item a `blood_bank` item so they can share one case
 * bill. Charges that differ by bed type are two items: the price-list code plus `R` (Regular) or `C` (Cabin), e.g.
 * `BCIP01R` and `BCIP01C`. The list repeats `BCIP01` for the "without Infusion Pump" charge, so that pair is `BCIP02`.
 * Needs `DepartmentSeeder`. Matched on `code`, so re-running adds missing items and leaves an edited rate alone.
 */
final class BloodBankTestItemSeeder extends Seeder
{
    /**
     * @var array<string, array{0: string, 1: int}> code => [name, rate in taka]
     */
    private const array ITEMS = [
        'BB001' => ['Blood Grouping, Screening & Cross Matching', 1500],
        'WB01' => ['Whole Blood', 1700],
        'BBT03' => ['PCV/PRBC/RCC', 2600],
        'FP001' => ['Fresh Plasma (FP)', 2600],
        'FFP01' => ['Fresh Frozen Plasma (FFP)', 2600],
        'SWPF01' => ['Stock WB/PRBC/FFP', 1000],
        'PC001' => ['Platelet Concentrate (PC)', 3000],
        'PC002' => ['Platelet (4:1)', 12000],
        'PRP01' => ['Platelet Rich Plasma', 3000],
        'PA01' => ['Platelet Apheresis', 24000],
        'APRP01' => ['Autologous PRP', 1000],
        'TP01' => ['Therapeutic Phlebotomy', 1000],
        'CP01' => ['Cryo Precipitate', 2600],
        'CPP01' => ['Cryo Poor Plasma', 2600],
        'TP02' => ['Thalassemia Package', 2500],
        'BCBT01R' => ['Bed Charge for Blood Transfusion - Regular', 1500],
        'BCBT01C' => ['Bed Charge for Blood Transfusion - Cabin', 2000],
        'BCIP01R' => ['Bed Charge for Medication with Infusion Pump - Regular', 1800],
        'BCIP01C' => ['Bed Charge for Medication with Infusion Pump - Cabin', 2200],
        'BCIP02R' => ['Bed Charge for Medication without Infusion Pump - Regular', 1500],
        'BCIP02C' => ['Bed Charge for Medication without Infusion Pump - Cabin', 1800],
        'ISO01R' => ['Infusion (Saline/Others) - Regular', 1500],
        'ISO01C' => ['Infusion (Saline/Others) - Cabin', 2000],
    ];

    public function run(): void
    {
        $department = Department::query()->where('code', 'BB')->first()
            ?? throw new RuntimeException('Department BB is missing; run DepartmentSeeder first.');

        foreach (self::ITEMS as $code => [$name, $rateInTaka]) {
            TestItem::query()->firstOrCreate(['code' => $code], [
                'department_id' => $department->id,
                'department_name' => $department->name,
                'category' => TestItemCategory::BloodBank,
                'name' => $name,
                'rate' => $rateInTaka * 100,
                'is_active' => true,
            ]);
        }
    }
}
