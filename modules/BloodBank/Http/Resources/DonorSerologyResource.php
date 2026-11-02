<?php

namespace Modules\BloodBank\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\BloodBank\Data\DonorSerologyRow;
use Modules\BloodBank\Data\DonorSerologyView;
use Modules\BloodBank\Data\SubjectView;
use Modules\BloodBank\Enums\CaseDonorStatus;
use Modules\BloodBank\Enums\SampleSubject;
use Modules\BloodBank\Enums\SerologyDecision;
use Modules\BloodBank\Enums\SerologyTest;
use Modules\BloodBank\Models\Sample;
use Modules\BloodBank\Models\TestResult;
use Modules\Shared\Auth\Models\User;

/**
 * The donor serology screen: the information panel (`case`, `patient`, `invoice`) and one row per case donor in
 * sequence order. A row's `sample` is null until the donor sample is collected and `serology` is null until the
 * form is saved; `decision` is the serology decision (`accepted`, `deferred`, or null when not decided). A donor
 * deferred at the physical exam has `case_donor_status` `deferred` with no `serology`. The form can be saved
 * while `can_record`; `serology.version` above 1 means a result was amended.
 *
 * @property-read DonorSerologyView $resource
 */
final class DonorSerologyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'case' => CaseSummaryResource::make($this->resource->case)->toArray($request),
            'patient' => $this->resource->patient?->toArray(),
            'invoice' => $this->resource->invoice?->toArray(),
            'donors' => array_map(function (DonorSerologyRow $row) use ($request): array {
                $serology = $this->serology($row->sample);

                return [
                    ...SubjectResource::make(new SubjectView(SampleSubject::Donor, caseDonor: $row->caseDonor))->toArray($request),
                    'sample' => $row->sample === null ? null : [
                        'id' => $row->sample->id,
                        'sample_no' => $row->sample->sample_no,
                        'status' => $row->sample->status->value,
                        'collected_at' => $row->sample->collected_at->toIso8601String(),
                    ],
                    'serology' => $serology,
                    'decision' => $serology === null ? null : $this->decision($row->caseDonor->status)?->value,
                    'deferral_reason' => $row->caseDonor->deferral_reason,
                    'assessed_at' => $row->caseDonor->assessed_at?->toIso8601String(),
                    'assessed_by' => $this->user($row->caseDonor->assessor),
                    'can_record' => $row->canRecord,
                ];
            }, $this->resource->donors),
        ];
    }

    private function decision(CaseDonorStatus $status): ?SerologyDecision
    {
        return match ($status) {
            CaseDonorStatus::Eligible, CaseDonorStatus::Collected => SerologyDecision::Accepted,
            CaseDonorStatus::Deferred => SerologyDecision::Deferred,
            default => null,
        };
    }

    /**
     * The current result of each test, plus who saved the latest one.
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
            ...$values,
            'version' => $results->max('version'),
            'tested_at' => $latest->tested_at->toIso8601String(),
            'tested_by' => $this->user($latest->tester),
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function user(?User $user): ?array
    {
        return $user === null ? null : ['id' => $user->id, 'name' => $user->name];
    }
}
