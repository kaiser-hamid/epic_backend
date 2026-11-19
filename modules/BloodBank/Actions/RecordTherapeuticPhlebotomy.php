<?php

namespace Modules\BloodBank\Actions;

use Illuminate\Support\Facades\DB;
use Modules\BloodBank\Data\BloodCollectionView;
use Modules\BloodBank\Data\RecordTherapeuticPhlebotomyData;
use Modules\BloodBank\Enums\BloodBankAuditAction;
use Modules\BloodBank\Enums\CaseService;
use Modules\BloodBank\Enums\CaseStatus;
use Modules\BloodBank\Exceptions\CaseNotOpen;
use Modules\BloodBank\Exceptions\NotAPhlebotomyCase;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Models\Phlebotomy;
use Modules\BloodBank\Queries\ShowBloodCollection;
use Modules\BloodBank\Services\CaseCloser;
use Modules\Shared\Audit\Contracts\AuditLogger;
use Modules\Shared\Auth\Models\User;

/**
 * "Mark done" for a Therapeutic Phlebotomy: blood is drawn from the patient and destroyed, with no sample and no
 * bag. Records the volume and the optional pre/post vitals, then closes the case. Once only: the closed case
 * refuses a second record (`CASE_NOT_OPEN`).
 */
final class RecordTherapeuticPhlebotomy
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly CaseCloser $caseCloser,
        private readonly ShowBloodCollection $showBloodCollection,
    ) {}

    /**
     * @throws CaseNotOpen
     * @throws NotAPhlebotomyCase
     */
    public function handle(User $actor, PatientCase $case, RecordTherapeuticPhlebotomyData $data): BloodCollectionView
    {
        $case = DB::transaction(function () use ($actor, $case, $data): PatientCase {
            $case = PatientCase::query()->lockForUpdate()->findOrFail($case->id);

            if ($case->service !== CaseService::TherapeuticPhlebotomy) {
                throw new NotAPhlebotomyCase;
            }

            if ($case->status !== CaseStatus::Open) {
                throw new CaseNotOpen;
            }

            $phlebotomy = Phlebotomy::query()->create([
                'case_id' => $case->id,
                'volume_ml' => $data->volumeMl,
                'pre_pulse_bpm' => $data->pre?->pulseBpm,
                'pre_bp_systolic' => $data->pre?->bpSystolic,
                'pre_bp_diastolic' => $data->pre?->bpDiastolic,
                'pre_temperature_f' => $data->pre?->temperatureF,
                'post_pulse_bpm' => $data->post?->pulseBpm,
                'post_bp_systolic' => $data->post?->bpSystolic,
                'post_bp_diastolic' => $data->post?->bpDiastolic,
                'post_temperature_f' => $data->post?->temperatureF,
                'notes' => $data->notes,
                'performed_at' => $data->performedAt ?? now(),
                'performed_by' => $actor->id,
            ]);

            $this->auditLogger->record(BloodBankAuditAction::PhlebotomyRecorded, $phlebotomy, newValues: [
                ...$phlebotomy->only([
                    'volume_ml', 'pre_pulse_bpm', 'pre_bp_systolic', 'pre_bp_diastolic', 'pre_temperature_f',
                    'post_pulse_bpm', 'post_bp_systolic', 'post_bp_diastolic', 'post_temperature_f', 'notes', 'performed_at',
                ]),
                'case_no' => $case->case_no,
            ]);

            $this->caseCloser->close($case, $actor, 'therapeutic_phlebotomy');

            return $case->setRelations([]);
        });

        return $this->showBloodCollection->get($case);
    }
}
