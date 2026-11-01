<?php

namespace Modules\BloodBank\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\BloodBank\Data\DonorExaminationRow;
use Modules\BloodBank\Data\DonorExaminationView;
use Modules\BloodBank\Data\SubjectView;
use Modules\BloodBank\Enums\CaseDonorStatus;
use Modules\BloodBank\Enums\ExaminationDecision;
use Modules\BloodBank\Enums\SampleSubject;
use Modules\BloodBank\Models\CaseDonor;
use Modules\BloodBank\Models\DonorScreening;
use Modules\Shared\Auth\Models\User;

/**
 * The donor examination screen: the information panel (`case`, `patient`, `invoice`) and one row per case
 * donor in sequence order. A row's `examination` is null until the form is saved; `decision` is `accepted`
 * (status `fit` or later), `deferred`, or null (not decided). The form can be saved while `can_examine`.
 * Measurements are JSON numbers; BP is `bp_systolic` / `bp_diastolic`.
 *
 * @property-read DonorExaminationView $resource
 */
final class DonorExaminationResource extends JsonResource
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
            'donors' => array_map(fn (DonorExaminationRow $row): array => [
                ...SubjectResource::make(new SubjectView(SampleSubject::Donor, caseDonor: $row->caseDonor))->toArray($request),
                'decision' => $this->decision($row->caseDonor)?->value,
                'deferral_reason' => $row->caseDonor->deferral_reason,
                'assessed_at' => $row->caseDonor->assessed_at?->toIso8601String(),
                'assessed_by' => $this->user($row->caseDonor->assessor),
                'examination' => $this->examination($row->caseDonor->screening),
                'can_examine' => $row->canExamine,
            ], $this->resource->donors),
        ];
    }

    /**
     * The physical-exam decision as the physician entered it. A later serology deferral also shows as
     * `deferred` here; `case_donor_status` tells the two apart.
     */
    private function decision(CaseDonor $caseDonor): ?ExaminationDecision
    {
        return match ($caseDonor->status) {
            CaseDonorStatus::Registered => null,
            CaseDonorStatus::Deferred => ExaminationDecision::Deferred,
            default => ExaminationDecision::Accepted,
        };
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
            'pulse_bpm' => $screening->pre_pulse_bpm,
            'bp_systolic' => $screening->pre_bp_systolic,
            'bp_diastolic' => $screening->pre_bp_diastolic,
            'temperature_f' => $this->number($screening->pre_temperature_f),
            'examined_at' => $screening->examined_at->toIso8601String(),
            'examined_by' => $this->user($screening->examiner),
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
