<?php

namespace Modules\BloodBank\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\BloodBank\Enums\BillableItemKind;
use Modules\BloodBank\Enums\CaseService;
use Modules\BloodBank\Models\BillableItem;
use Modules\BloodBank\Models\ComponentType;
use Modules\Shared\TestCatalog\Enums\TestItemCategory;
use Modules\Shared\TestCatalog\Models\TestItem;
use RuntimeException;

/**
 * What each Blood Bank price-list item means to Blood Bank, by item code (`BloodBankTestItemSeeder` and
 * `ComponentTypeSeeder` must run first). Items not listed (cross matching, bed charges, infusion) are plain
 * services. Safe to re-run: rows are matched by catalog item.
 */
final class BillableItemSeeder extends Seeder
{
    /**
     * Component items: item code => [component type code, units per billed quantity]. Platelet (4:1) needs four
     * platelet units (four donors); the Thalassemia Package is PCV for the patient.
     *
     * @var array<string, array{0: string, 1: int}>
     */
    private const array COMPONENTS = [
        'WB01' => ['WB', 1],
        'BBT03' => ['PCV', 1],
        'TH01' => ['PCV', 1],
        'FP001' => ['FP', 1],
        'FFP01' => ['FFP', 1],
        'PC001' => ['PLATELET', 1],
        'PC002' => ['PLATELET', 4],
        'PRP01' => ['PRP', 1],
        'PA01' => ['PA', 1],
        'CP01' => ['CRYO', 1],
        'CPP01' => ['PLASMA', 1],
    ];

    private const string AUTOLOGOUS_PRP = 'APRP01';

    private const string THERAPEUTIC_PHLEBOTOMY = 'TP01';

    private const string STORAGE_FEE = 'SWPF01';

    public function run(): void
    {
        $componentTypes = ComponentType::query()->pluck('id', 'code');

        foreach (self::COMPONENTS as $itemCode => [$componentCode, $unitsPerQuantity]) {
            $this->upsert($itemCode, BillableItemKind::Component, [
                'component_type_id' => $componentTypes[$componentCode]
                    ?? throw new RuntimeException("Component type {$componentCode} is missing; run ComponentTypeSeeder first."),
                'units_per_quantity' => $unitsPerQuantity,
            ]);
        }

        $this->upsert(self::AUTOLOGOUS_PRP, BillableItemKind::Procedure, ['service' => CaseService::AutologousPrp]);
        $this->upsert(self::THERAPEUTIC_PHLEBOTOMY, BillableItemKind::Procedure, ['service' => CaseService::TherapeuticPhlebotomy]);
        $this->upsert(self::STORAGE_FEE, BillableItemKind::StorageFee);
    }

    /**
     * @param  array<string, mixed>  $meaning
     */
    private function upsert(string $itemCode, BillableItemKind $kind, array $meaning = []): void
    {
        $item = TestItem::query()
            ->where('category', TestItemCategory::BloodBank)
            ->where('code', $itemCode)
            ->first()
            ?? throw new RuntimeException("Blood Bank test item {$itemCode} is missing; run BloodBankTestItemSeeder first.");

        BillableItem::query()->updateOrCreate(['test_item_id' => $item->id], [
            'test_item_code' => $item->code,
            'test_item_name' => $item->name,
            'kind' => $kind,
            'component_type_id' => null,
            'units_per_quantity' => 1,
            'service' => null,
            ...$meaning,
            'is_active' => true,
        ]);
    }
}
