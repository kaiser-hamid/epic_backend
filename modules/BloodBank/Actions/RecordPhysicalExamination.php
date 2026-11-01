<?php

namespace Modules\BloodBank\Actions;

use Illuminate\Support\Facades\DB;
use Modules\BloodBank\Data\DonorExaminationView;
use Modules\BloodBank\Data\RecordPhysicalExaminationData;
use Modules\BloodBank\Enums\BloodBankAuditAction;
use Modules\BloodBank\Enums\CaseDonorStatus;
use Modules\BloodBank\Enums\CaseStatus;
use Modules\BloodBank\Exceptions\CaseDonorNotInCase;
use Modules\BloodBank\Exceptions\CaseNotOpen;
use Modules\BloodBank\Exceptions\ExaminationLocked;
use Modules\BloodBank\Models\CaseDonor;
use Modules\BloodBank\Models\DonorScreening;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Queries\ShowDonorExamination;
use Modules\BloodBank\Services\DonorExaminationLock;
use Modules\Shared\Audit\Contracts\AuditLogger;
use Modules\Shared\Auth\Models\User;

/**
 * Saves the physician's pre-donation physical examination for one case donor together with the required Accept
 * (`fit`) / Defer decision. The form replaces what was saved before, until the donor sample is drawn (the lock
 * keeps a donor from being moved back from a later stage); a correction is audited with old and new values.
 */
final class RecordPhysicalExamination
{
    private const array EXAMINATION_FIELDS = [
        'weight_kg', 'height_cm', 'hemoglobin_g_dl', 'pre_pulse_bpm', 'pre_bp_systolic', 'pre_bp_diastolic',
        'pre_temperature_f', 'examined_by', 'examined_at',
    ];

    public function __construct(
        private readonly DonorExaminationLock $examinationLock,
        private readonly AuditLogger $auditLogger,
        private readonly ShowDonorExamination $showDonorExamination,
    ) {}

    /**
     * @throws CaseNotOpen
     * @throws CaseDonorNotInCase
     * @throws ExaminationLocked
     */
    public function handle(User $actor, PatientCase $case, CaseDonor $caseDonor, RecordPhysicalExaminationData $data): DonorExaminationView
    {
        $case = DB::transaction(function () use ($actor, $case, $caseDonor, $data): PatientCase {
            $case = PatientCase::query()->lockForUpdate()->findOrFail($case->id);

            if ($case->status !== CaseStatus::Open) {
                throw new CaseNotOpen;
            }

            if ($caseDonor->case_id !== $case->id) {
                throw new CaseDonorNotInCase;
            }

            $caseDonor = CaseDonor::query()->lockForUpdate()->findOrFail($caseDonor->id);

            if ($this->examinationLock->isLocked($caseDonor)) {
                throw new ExaminationLocked;
            }

            $screening = DonorScreening::query()->firstOrNew(['case_donor_id' => $caseDonor->id]);
            $isAmendment = $screening->exists;
            $oldValues = $isAmendment ? $this->snapshot($screening, $caseDonor) : [];

            $screening->fill([
                'weight_kg' => $data->weightKg,
                'height_cm' => $data->heightCm,
                'hemoglobin_g_dl' => $data->hemoglobinGDl,
                'pre_pulse_bpm' => $data->pulseBpm,
                'pre_bp_systolic' => $data->bpSystolic,
                'pre_bp_diastolic' => $data->bpDiastolic,
                'pre_temperature_f' => $data->temperatureF,
                'examined_by' => $actor->id,
                'examined_at' => now(),
            ])->save();

            $this->recordDecision($actor, $caseDonor, $data);

            $this->auditLogger->record(
                $isAmendment ? BloodBankAuditAction::DonorExaminationAmended : BloodBankAuditAction::DonorExamined,
                $caseDonor,
                oldValues: $oldValues,
                newValues: [...$this->snapshot($screening, $caseDonor), 'case_no' => $case->case_no],
            );

            return $case->setRelations([]);
        });

        return $this->showDonorExamination->get($case);
    }

    /**
     * The assessor and time change only when the decision itself changes, so re-saving a corrected value does
     * not re-sign an earlier decision.
     */
    private function recordDecision(User $actor, CaseDonor $caseDonor, RecordPhysicalExaminationData $data): void
    {
        $status = $data->decision->caseDonorStatus();
        $deferralReason = $status === CaseDonorStatus::Deferred ? $data->deferralReason : null;

        if ($status === $caseDonor->status && $deferralReason === $caseDonor->deferral_reason) {
            return;
        }

        $caseDonor->update([
            'status' => $status,
            'deferral_reason' => $deferralReason,
            'assessed_by' => $actor->id,
            'assessed_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(DonorScreening $screening, CaseDonor $caseDonor): array
    {
        return [
            ...$screening->only(self::EXAMINATION_FIELDS),
            'status' => $caseDonor->status->value,
            'deferral_reason' => $caseDonor->deferral_reason,
        ];
    }
}
