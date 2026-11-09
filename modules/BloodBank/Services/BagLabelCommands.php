<?php

namespace Modules\BloodBank\Services;

use Carbon\CarbonInterface;
use Modules\BloodBank\Data\BagView;
use Modules\BloodBank\Data\UnitView;
use Modules\BloodBank\Models\ComponentType;
use Modules\Shared\Billing\Contracts\InvoiceDirectory;
use Modules\Shared\Patient\Data\PatientSummary;
use Modules\Shared\Patient\Enums\BloodGroup;
use Modules\Shared\Patient\Enums\Gender;
use Modules\Shared\Printing\Contracts\LabelRenderer;

/**
 * The printer command for each blood bag label (`resources/label/bb_bag_label.ezpx`), each value cut to the length
 * its template variable declares. The same label is printed at blood collection (the bag, whole blood) and at
 * component preparation (each unit):
 * - `V00` (barcode): the bag no, or the unit no
 * - `V01`, `V02`: collection and expiry date (`d/m/Y`); a collected bag expires as whole blood would
 * - `V03` to `V05`: the patient's name, age (`25Y`) and gender (`M`); blank for a unit not reserved for a patient
 * - `V06`: the donor's blood group (`A+ve`, `O-ve`)
 * - `V07`: the component code (`WB`, `PLASMA`, `PLATELET`)
 * - `V08`: the donor's name
 * - `V09`, `V10`: the referring doctor and number of the case's invoice (the donor's own when billed separately)
 */
final class BagLabelCommands
{
    private const string TEMPLATE = 'bb_bag_label.ezpx';

    public function __construct(
        private readonly LabelRenderer $labels,
        private readonly InvoiceDirectory $invoices,
    ) {}

    /**
     * @param  list<BagView>  $bags  with `collection.caseDonor.donor` and `collection.caseDonor.patientCase` loaded
     * @return array<int, string> keyed by collection id
     */
    public function forBags(array $bags): array
    {
        $billingIds = [];
        foreach ($bags as $view) {
            $caseDonor = $view->collection->caseDonor;
            $billingIds[$view->collection->id] = $caseDonor->billing_id ?? $caseDonor->patientCase->billing_id;
        }

        $invoices = $this->invoices->summaries(array_values(array_unique(array_filter($billingIds))));
        $wholeBlood = ComponentType::query()->where('code', ComponentType::WHOLE_BLOOD)->first();

        $commands = [];
        foreach ($bags as $view) {
            $collection = $view->collection;
            $invoice = $invoices[$billingIds[$collection->id]] ?? null;

            $commands[$collection->id] = $this->labels->render(self::TEMPLATE, [
                'V00' => $collection->bag_no,
                'V01' => $this->date($collection->collected_at),
                'V02' => $wholeBlood === null ? '' : $this->date($collection->collected_at->copy()->addHours($wholeBlood->shelf_life_hours)),
                ...$this->patientValues($view->patient),
                'V06' => $this->bloodGroup($collection->caseDonor->donor->blood_group),
                'V07' => ComponentType::WHOLE_BLOOD,
                'V08' => $collection->caseDonor->donor->name,
                'V09' => $invoice?->referredBy ?? '',
                'V10' => $invoice?->invoiceNo ?? '',
            ]);
        }

        return $commands;
    }

    /**
     * @param  list<UnitView>  $units  with `componentType`, `collection.caseDonor.donor` and `patientCase` loaded
     * @return array<int, string> keyed by unit id
     */
    public function forUnits(array $units): array
    {
        $billingIds = [];
        foreach ($units as $view) {
            $billingIds[$view->unit->id] = $view->unit->patientCase?->billing_id;
        }

        $invoices = $this->invoices->summaries(array_values(array_unique(array_filter($billingIds))));

        $commands = [];
        foreach ($units as $view) {
            $unit = $view->unit;
            $invoice = $invoices[$billingIds[$unit->id]] ?? null;

            $commands[$unit->id] = $this->labels->render(self::TEMPLATE, [
                'V00' => $unit->unit_no,
                'V01' => $this->date($unit->collection->collected_at),
                'V02' => $this->date($unit->expires_at),
                ...$this->patientValues($view->patient),
                'V06' => $this->bloodGroup($unit->blood_group ?? $unit->collection->caseDonor->donor->blood_group),
                'V07' => $unit->componentType->code,
                'V08' => $unit->collection->caseDonor->donor->name,
                'V09' => $invoice?->referredBy ?? '',
                'V10' => $invoice?->invoiceNo ?? '',
            ]);
        }

        return $commands;
    }

    /**
     * @return array{V03: string, V04: string, V05: string}
     */
    private function patientValues(?PatientSummary $patient): array
    {
        return [
            'V03' => $patient?->name ?? '',
            'V04' => $patient?->ageLabel() ?? '',
            'V05' => match ($patient?->gender) {
                Gender::Male => 'M',
                Gender::Female => 'F',
                Gender::Other => 'O',
                null => '',
            },
        ];
    }

    private function bloodGroup(?BloodGroup $bloodGroup): string
    {
        return $bloodGroup === null ? '' : $bloodGroup->abo().($bloodGroup->rhesus() === 'Positive' ? '+ve' : '-ve');
    }

    private function date(CarbonInterface $date): string
    {
        return $date->format('d/m/Y');
    }
}
