<?php

namespace Modules\BloodBank\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\BloodBank\Data\BloodIssueDonorRow;
use Modules\BloodBank\Data\BloodIssueUnitRow;
use Modules\BloodBank\Data\BloodIssueView;
use Modules\BloodBank\Data\SubjectView;
use Modules\BloodBank\Enums\SampleSubject;
use Modules\BloodBank\Enums\SerologyTest;
use Modules\BloodBank\Enums\UnitStatus;
use Modules\BloodBank\Models\CaseComponent;
use Modules\BloodBank\Models\Collection;
use Modules\BloodBank\Models\Crossmatch;
use Modules\BloodBank\Models\DonorScreening;
use Modules\BloodBank\Models\Sample;
use Modules\BloodBank\Models\TestResult;
use Modules\Shared\Auth\Models\User;

/**
 * The blood issue (Compatibility Test Report) screen: everything about the case.
 *  - `case` with `supplied_at` and the billed `components`, each with `reserved_count`, `issued_count` and
 *    `remaining_count` (billed minus issued; the case closes when every line reaches 0).
 *  - `patient`, `invoice`; `patient_sample` (null until drawn) with its current `blood_group` (null until entered).
 *  - `donors` in sequence order: decision, `invoice_no` (an added donor's own bill), `examination` with `pre` and
 *    `post` vitals (`post` null until recorded), `serology` (null until saved), `bag` (null until collected) and
 *    `needs_post_vitals` (the Issue form must send this donor's vitals when one of its units is issued).
 *  - `units` reserved for or issued to the case, each with its current `crossmatch` (null until issued),
 *    `compatibility_report_print_count` and `can_issue`.
 * "Issue Blood" is enabled while `can_issue`.
 *
 * @property-read BloodIssueView $resource
 */
final class BloodIssueResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $view = $this->resource;
        $case = $view->case;
        $statusCounts = collect($view->units)
            ->groupBy(fn (BloodIssueUnitRow $row): string => $row->unit->unit->component_type_id.':'.$row->unit->unit->status->value)
            ->map->count();

        return [
            'case' => [
                ...CaseSummaryResource::make($case)->toArray($request),
                'supplied_at' => $case->supplied_at?->toIso8601String(),
                'components' => $case->components->sortBy('id')->map(function (CaseComponent $component) use ($request, $statusCounts): array {
                    $issued = $statusCounts->get($component->component_type_id.':'.UnitStatus::Issued->value, 0);

                    return [
                        ...CaseComponentResource::make($component)->toArray($request),
                        'reserved_count' => $statusCounts->get($component->component_type_id.':'.UnitStatus::Reserved->value, 0),
                        'issued_count' => $issued,
                        'remaining_count' => max(0, $component->quantity - $issued),
                    ];
                })->values()->all(),
            ],
            'patient' => $view->patient?->toArray(),
            'invoice' => $view->invoice?->toArray(),
            'patient_sample' => $this->patientSample($view->patientSample, $view->patientBloodGroup),
            'donors' => array_map(fn (BloodIssueDonorRow $row): array => [
                ...SubjectResource::make(new SubjectView(SampleSubject::Donor, caseDonor: $row->caseDonor))->toArray($request),
                'invoice_no' => $row->caseDonor->billing_id === null ? null : ($view->donorInvoices[$row->caseDonor->billing_id] ?? null)?->invoiceNo,
                'deferral_reason' => $row->caseDonor->deferral_reason,
                'assessed_at' => $row->caseDonor->assessed_at?->toIso8601String(),
                'assessed_by' => $this->user($row->caseDonor->assessor),
                'examination' => $this->examination($row->caseDonor->screening),
                'serology' => $this->serology($row->sample),
                'bag' => $this->bag($row->bag),
                'needs_post_vitals' => $row->needsPostVitals,
            ], $view->donors),
            'units' => array_map(fn (BloodIssueUnitRow $row): array => [
                ...UnitResource::make($row->unit)->toArray($request),
                'crossmatch' => $this->crossmatch($row->crossmatch),
                'compatibility_report_print_count' => $row->reportPrintCount,
                'can_issue' => $row->canIssue,
            ], $view->units),
            'can_issue' => $view->canIssue(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function patientSample(?Sample $sample, ?TestResult $bloodGroup): ?array
    {
        if ($sample === null) {
            return null;
        }

        return [
            'id' => $sample->id,
            'sample_no' => $sample->sample_no,
            'status' => $sample->status->value,
            'collected_at' => $sample->collected_at->toIso8601String(),
            'blood_group' => $bloodGroup === null ? null : [
                'value' => $bloodGroup->result_value,
                'version' => $bloodGroup->version,
                'tested_at' => $bloodGroup->tested_at->toIso8601String(),
                'tested_by' => $this->user($bloodGroup->tester),
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function examination(?DonorScreening $screening): ?array
    {
        if ($screening === null) {
            return null;
        }

        return [
            'weight_kg' => $this->number($screening->weight_kg),
            'height_cm' => $this->number($screening->height_cm),
            'hemoglobin_g_dl' => $this->number($screening->hemoglobin_g_dl),
            'pre' => [
                'pulse_bpm' => $screening->pre_pulse_bpm,
                'bp_systolic' => $screening->pre_bp_systolic,
                'bp_diastolic' => $screening->pre_bp_diastolic,
                'temperature_f' => $this->number($screening->pre_temperature_f),
            ],
            'examined_at' => $screening->examined_at->toIso8601String(),
            'examined_by' => $this->user($screening->examiner),
            'post' => $screening->post_recorded_at === null ? null : [
                'pulse_bpm' => $screening->post_pulse_bpm,
                'bp_systolic' => $screening->post_bp_systolic,
                'bp_diastolic' => $screening->post_bp_diastolic,
                'temperature_f' => $this->number($screening->post_temperature_f),
                'recorded_at' => $screening->post_recorded_at->toIso8601String(),
                'recorded_by' => $this->user($screening->postRecorder),
            ],
        ];
    }

    /**
     * The current result of each serology test on the donor sample, plus who saved the latest one.
     *
     * @return array<string, mixed>|null
     */
    private function serology(?Sample $sample): ?array
    {
        $results = $sample?->results->where('is_current', true);

        if ($results === null || $results->isEmpty()) {
            return null;
        }

        $byCode = $results->keyBy(fn (TestResult $result): string => $result->testDefinition->code);
        $latest = $results->sortByDesc('tested_at')->first();
        $values = [];

        foreach (SerologyTest::cases() as $test) {
            $values[$test->field()] = $byCode->get($test->value)?->result_value;
        }

        return [
            'sample_no' => $sample->sample_no,
            ...$values,
            'version' => $results->max('version'),
            'tested_at' => $latest->tested_at->toIso8601String(),
            'tested_by' => $this->user($latest->tester),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function bag(?Collection $bag): ?array
    {
        return $bag === null ? null : [
            'id' => $bag->id,
            'bag_no' => $bag->bag_no,
            'status' => $bag->status->value,
            'volume_ml' => $bag->volume_ml,
            'collected_at' => $bag->collected_at->toIso8601String(),
            'collected_by' => $this->user($bag->collector),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function crossmatch(?Crossmatch $crossmatch): ?array
    {
        return $crossmatch === null ? null : [
            'id' => $crossmatch->id,
            'version' => $crossmatch->version,
            'major_result' => $crossmatch->major_result->value,
            'minor_result' => $crossmatch->minor_result->value,
            'is_compatible' => $crossmatch->isCompatible(),
            'remarks' => $crossmatch->remarks,
            'status' => $crossmatch->status->value,
            'tested_at' => $crossmatch->tested_at->toIso8601String(),
            'tested_by' => $this->user($crossmatch->tester),
            'reviewed_at' => $crossmatch->reviewed_at?->toIso8601String(),
            'reviewed_by' => $this->user($crossmatch->reviewer),
        ];
    }

    private function number(?string $decimal): ?float
    {
        return $decimal === null ? null : (float) $decimal;
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function user(?User $user): ?array
    {
        return $user === null ? null : ['id' => $user->id, 'name' => $user->name];
    }
}
