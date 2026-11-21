<?php

namespace Modules\BloodBank\Services;

use Carbon\CarbonInterface;
use Modules\BloodBank\Data\UnitView;
use Modules\BloodBank\Enums\PrintDocumentType;
use Modules\Shared\Billing\Contracts\InvoiceDirectory;
use Modules\Shared\Patient\Data\PatientSummary;
use Modules\Shared\Patient\Enums\BloodGroup;
use Modules\Shared\Patient\Enums\Gender;
use Modules\Shared\Printing\Contracts\LabelRenderer;

/**
 * The printer command for each unit label, each value cut to the length its template variable declares. A unit
 * reserved for or issued to a patient gets the patient label (`resources/label/bb_bag_label.ezpx`; for a stock unit
 * it is printed over the storage label):
 * - `V00` (barcode): the unit no
 * - `V01`, `V02`: collection and expiry date (`d/m/Y`)
 * - `V03` to `V05`: the patient's name, age (`25Y`) and gender (`M`)
 * - `V06`: the donor's blood group (`A+ve`, `O-ve`)
 * - `V07`: the component code (`WB`, `PCV`, `PLATELET`)
 * - `V08`: the donor's name
 * - `V09`, `V10`: the referring doctor and number of the case's invoice
 *
 * A stock unit (also one held for a case while its serology is pending) gets the storage label
 * (`resources/label/bb_bg_storage_bag_label.ezpx`):
 * - `V00` (barcode): the unit no; `V01` (barcode): the donor no
 * - `V02` to `V04`: the donor's name (at most 24 characters), age (`32Y`) and gender (`Male`)
 * - `V05`: the blood group; `V06`: the component code
 * - `V07`, `V08`: collection and expiry date (`d/m/Y`)
 */
final class UnitLabelCommands
{
    private const string PATIENT_TEMPLATE = 'bb_bag_label.ezpx';

    private const string STORAGE_TEMPLATE = 'bb_bg_storage_bag_label.ezpx';

    private const int STORAGE_DONOR_NAME_LENGTH = 24;

    public function __construct(
        private readonly LabelRenderer $labels,
        private readonly InvoiceDirectory $invoices,
    ) {}

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
            $donor = $unit->collection->caseDonor->donor;
            $bloodGroup = $this->bloodGroup($unit->blood_group ?? $donor->blood_group);

            if (PrintDocumentType::unitLabelFor($unit->status) === PrintDocumentType::StorageLabel) {
                $commands[$unit->id] = $this->labels->render(self::STORAGE_TEMPLATE, [
                    'V00' => $unit->unit_no,
                    'V01' => $donor->donor_no,
                    'V02' => mb_substr($donor->name, 0, self::STORAGE_DONOR_NAME_LENGTH),
                    'V03' => $donor->ageLabel() ?? '',
                    'V04' => $donor->gender?->label() ?? '',
                    'V05' => $bloodGroup,
                    'V06' => $unit->componentType->code,
                    'V07' => $this->date($unit->collection->collected_at),
                    'V08' => $this->date($unit->expires_at),
                ]);

                continue;
            }

            $invoice = $invoices[$billingIds[$unit->id]] ?? null;

            $commands[$unit->id] = $this->labels->render(self::PATIENT_TEMPLATE, [
                'V00' => $unit->unit_no,
                'V01' => $this->date($unit->collection->collected_at),
                'V02' => $this->date($unit->expires_at),
                ...$this->patientValues($view->patient),
                'V06' => $bloodGroup,
                'V07' => $unit->componentType->code,
                'V08' => $donor->name,
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
