<?php

namespace Modules\BloodBank\Services;

use Modules\BloodBank\Data\SampleView;
use Modules\BloodBank\Models\PatientCase;
use Modules\Shared\Billing\Contracts\InvoiceDirectory;
use Modules\Shared\Patient\Enums\Gender;
use Modules\Shared\Printing\Contracts\LabelRenderer;

/**
 * The barcode label printer command for each sample (`resources/label/bb_barcode_label.ezpx`), each value cut to
 * the length its template variable declares:
 * - `V00` (barcode): the sample no
 * - `V01`: `{invoice no without the branch code}|{sample no}`, the invoice being the subject's own (an additional
 *   donor's charge) or else the case's
 * - `V02`: the test item codes billed on that invoice, comma separated
 * - `V03`: `{M|F|O}-{age}Y {print time}`, e.g. `M-36Y 27/10/2026 08:16 PM`
 */
final class SampleLabelCommands
{
    private const string TEMPLATE = 'bb_barcode_label.ezpx';

    /**
     * Invoice numbers start with the branch code (`NB2610310001`); the label leaves it out.
     */
    private const int BRANCH_CODE_LENGTH = 2;

    public function __construct(
        private readonly LabelRenderer $labels,
        private readonly InvoiceDirectory $invoices,
    ) {}

    /**
     * @param  list<SampleView>  $samples
     * @return array<int, string> keyed by sample id
     */
    public function for(PatientCase $case, array $samples): array
    {
        if ($samples === []) {
            return [];
        }

        $billingIds = [];
        foreach ($samples as $view) {
            $billingIds[$view->sample->id] = $view->subject->caseDonor?->billing_id ?? $case->billing_id;
        }

        $uniqueBillingIds = array_values(array_unique(array_filter($billingIds)));
        $invoices = $this->invoices->summaries($uniqueBillingIds);
        $testItemCodes = $this->invoices->testItemCodes($uniqueBillingIds);
        $printedAt = now()->format('d/m/Y h:i A');

        $commands = [];
        foreach ($samples as $view) {
            $billingId = $billingIds[$view->sample->id];
            $sampleNo = $view->sample->sample_no;

            $commands[$view->sample->id] = $this->labels->render(self::TEMPLATE, [
                'V00' => $sampleNo,
                'V01' => substr($billingId === null ? '' : ($invoices[$billingId]->invoiceNo ?? ''), self::BRANCH_CODE_LENGTH).'|'.$sampleNo,
                'V02' => implode(',', $billingId === null ? [] : ($testItemCodes[$billingId] ?? [])),
                'V03' => $this->genderAndAge($view).' '.$printedAt,
            ]);
        }

        return $commands;
    }

    private function genderAndAge(SampleView $view): string
    {
        $donor = $view->subject->caseDonor?->donor;
        $gender = $donor !== null ? $donor->gender : $view->subject->patient?->gender;
        $age = $donor !== null ? $donor->currentAge() : $view->subject->patient?->ageYears;

        return match ($gender) {
            Gender::Male => 'M',
            Gender::Female => 'F',
            Gender::Other => 'O',
            null => '',
        }.'-'.($age === null ? '' : $age.'Y');
    }
}
