<?php

namespace Modules\BloodBank\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Str;
use Modules\BloodBank\Enums\SerologyTest;
use Modules\BloodBank\Enums\UnitStatus;
use Modules\BloodBank\Models\Crossmatch;
use Modules\BloodBank\Models\PatientCase;
use Modules\Shared\Billing\Contracts\InvoiceDirectory;
use Modules\Shared\Patient\Contracts\PatientDirectory;
use Modules\Shared\Patient\Enums\BloodGroup;
use Modules\Shared\Printing\Contracts\PdfRenderer;
use Modules\Shared\Printing\Data\PdfDocument;
use Modules\Shared\Printing\Data\PdfPageSetup;

/**
 * Builds the Compatibility Test Report PDF, one page per crossmatch (= per unit), from the sources in
 * workflow.md step 8; nothing on the report is stored twice. The layout follows the paper report and is printed
 * on letterhead paper (215 × 292 mm, the top 35 mm left for the pre-printed header). "Unit" on the report is the
 * unit's place in the order the case's units were issued (1, 2, …). Recording the print is the caller's job.
 */
final class CompatibilityReportBuilder
{
    private const string DATE = 'd/m/Y';

    public function __construct(
        private readonly PdfRenderer $pdfRenderer,
        private readonly PatientDirectory $patients,
        private readonly InvoiceDirectory $invoices,
        private readonly DonorScreeningResults $screeningResults,
        private readonly BloodIssueGate $issueGate,
    ) {}

    /**
     * @param  EloquentCollection<int, Crossmatch>  $crossmatches  in page order
     * @param  array<int, bool>  $isReprint  keyed by crossmatch id
     */
    public function build(PatientCase $case, EloquentCollection $crossmatches, array $isReprint = [], int $status = 200): PdfDocument
    {
        $crossmatches->loadMissing([
            'tester',
            'reviewer',
            'patientSample.results.testDefinition',
            'unit.componentType',
            'unit.collection.collector',
            'unit.collection.caseDonor.donor',
        ]);

        $patient = $this->patients->summaries([$case->patient_master_id])[$case->patient_master_id] ?? null;
        $invoice = $case->billing_id === null ? null : ($this->invoices->summaries([$case->billing_id])[$case->billing_id] ?? null);
        $sequence = $this->issueSequence($case);

        $pages = $crossmatches->map(function (Crossmatch $crossmatch) use ($case, $patient, $invoice, $isReprint, $sequence): array {
            $unit = $crossmatch->unit;
            $collection = $unit->collection;
            $donor = $collection->caseDonor->donor;
            $patientBloodGroup = BloodGroup::tryFrom((string) $this->issueGate->bloodGroupResult($crossmatch->patientSample)?->result_value);

            return [
                'is_reprint' => $isReprint[$crossmatch->id] ?? false,
                'patient' => [
                    'name' => $patient === null ? null : mb_strtoupper(trim(($patient->title?->label() ?? '').' '.$patient->name)),
                    'gender' => $patient?->gender?->label(),
                    'age' => $patient === null ? null : $this->age($patient->ageYears, $patient->ageMonths, $patient->ageDays),
                    'phone' => $patient?->phone,
                    'abo' => $patientBloodGroup?->abo(),
                    'rh' => $patientBloodGroup?->rhesus(),
                ],
                'invoice_no' => $invoice?->invoiceNo,
                'invoice_date' => $invoice?->billedAt->format(self::DATE),
                'result_date' => ($crossmatch->reviewed_at ?? $crossmatch->tested_at)->format(self::DATE),
                'referred_by' => $invoice === null ? null : ($invoice->isSelfReferral ? 'Self' : $invoice->referredBy),
                'transfusion_reason' => $case->transfusion_reason,
                'bag_no' => $collection->bag_no,
                'unit_sequence' => $sequence[$unit->id] ?? null,
                'component' => $unit->componentType->code,
                'collected_at' => $collection->collected_at->format(self::DATE),
                'expires_at' => $unit->expires_at->format(self::DATE),
                'collected_by' => $collection->collector->name,
                'donor' => [
                    'name' => mb_strtoupper($donor->name),
                    'age' => $this->age($donor->currentAge()),
                    'gender' => $donor->gender?->label(),
                    'abo' => $unit->blood_group?->abo(),
                    'rh' => $unit->blood_group?->rhesus(),
                ],
                'screening' => array_map(fn (array $test): array => [
                    'name' => $test['name'],
                    'method' => $test['method'],
                    'result' => $test['result'] === null ? null : SerologyTest::from($test['code'])->resultLabel($test['result']),
                ], $this->screeningResults->forCaseDonor($collection->caseDonor, includeUntested: true)),
                'compatibility' => $crossmatch->isCompatible()
                    ? 'Compatible (Major/Minor)'
                    : 'Major: '.$crossmatch->major_result->label().', Minor: '.$crossmatch->minor_result->label(),
                'remarks' => $crossmatch->remarks,
            ];
        })->values()->all();

        $content = $this->pdfRenderer->render('blood-bank::reports.compatibility', ['pages' => $pages], new PdfPageSetup(
            width: 215,
            height: 292,
            marginTop: 35,
            marginBottom: 30,
            marginLeft: 12.7,
            marginRight: 12.7,
            marginFooter: 18,
        ));

        return new PdfDocument($content, "CTR-{$case->case_no}.pdf", $status);
    }

    /**
     * Each issued unit's place in the issue order of the case, keyed by unit id. A unit's current crossmatch is
     * written when it is issued, so the crossmatch order is the issue order.
     *
     * @return array<int, int>
     */
    private function issueSequence(PatientCase $case): array
    {
        return Crossmatch::query()
            ->where('case_id', $case->id)
            ->where('is_current', true)
            ->whereHas('unit', fn (Builder $query) => $query->where('case_id', $case->id)->where('status', UnitStatus::Issued))
            ->orderBy('id')
            ->pluck('unit_id')
            ->values()
            ->mapWithKeys(fn (int $unitId, int $index): array => [$unitId => $index + 1])
            ->all();
    }

    /**
     * An age as printed on the report: "35 Years", or months / days for an infant.
     */
    private function age(?int $years, ?int $months = null, ?int $days = null): ?string
    {
        return match (true) {
            (bool) $years => $years.' '.Str::plural('Year', $years),
            (bool) $months => $months.' '.Str::plural('Month', $months),
            (bool) $days => $days.' '.Str::plural('Day', $days),
            default => null,
        };
    }
}
