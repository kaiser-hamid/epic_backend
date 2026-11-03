<?php

namespace Modules\BloodBank\Actions;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Modules\BloodBank\Data\CrossmatchUnitData;
use Modules\BloodBank\Data\IssueBloodData;
use Modules\BloodBank\Data\PostDonationVitalsData;
use Modules\BloodBank\Enums\BloodBankAuditAction;
use Modules\BloodBank\Enums\CaseStatus;
use Modules\BloodBank\Enums\PrintDocumentType;
use Modules\BloodBank\Enums\SampleStatus;
use Modules\BloodBank\Enums\SerologyTest;
use Modules\BloodBank\Enums\TestResultStatus;
use Modules\BloodBank\Enums\UnitStatus;
use Modules\BloodBank\Exceptions\AmendmentReasonRequired;
use Modules\BloodBank\Exceptions\CaseDonorNotInCase;
use Modules\BloodBank\Exceptions\CaseNotOpen;
use Modules\BloodBank\Exceptions\CrossmatchIncompatible;
use Modules\BloodBank\Exceptions\PatientSampleNotCollected;
use Modules\BloodBank\Exceptions\PostDonationVitalsRecorded;
use Modules\BloodBank\Exceptions\PostDonationVitalsRequired;
use Modules\BloodBank\Exceptions\UnitExpired;
use Modules\BloodBank\Exceptions\UnitNotInCase;
use Modules\BloodBank\Exceptions\UnitNotReserved;
use Modules\BloodBank\Models\CaseDonor;
use Modules\BloodBank\Models\Crossmatch;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Models\Sample;
use Modules\BloodBank\Models\TestDefinition;
use Modules\BloodBank\Models\TestResult;
use Modules\BloodBank\Models\Unit;
use Modules\BloodBank\Services\BloodIssueGate;
use Modules\BloodBank\Services\CompatibilityReportBuilder;
use Modules\BloodBank\Services\UnitMovementRecorder;
use Modules\Shared\Audit\Contracts\AuditLogger;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Printing\Contracts\PrintLogger;
use Modules\Shared\Printing\Data\PdfDocument;

/**
 * "Issue Blood": in one transaction, records what the Compatibility Test Report needs and hands the units over.
 *  1. The patient's blood group on the patient sample (versioned; a change needs an amendment reason).
 *  2. Post-donation vitals of the donors whose blood is issued, where not recorded yet (required for them).
 *  3. A crossmatch per unit (patient sample × unit), signed by the chosen Medical Technologist and Physician.
 *     Only a unit compatible on both major and minor can be issued; the physician's call, never automatic.
 *  4. Each unit goes reserved → issued and leaves its storage location, with a movement row.
 *  5. The case's supply time is set; the case closes when every billed component is issued.
 *  6. One report page per unit is logged as printed and the PDF is returned.
 * The PDF is rendered inside the transaction, so a failed render issues nothing and the user can simply retry.
 */
final class IssueBlood
{
    public function __construct(
        private readonly BloodIssueGate $issueGate,
        private readonly UnitMovementRecorder $movements,
        private readonly AuditLogger $auditLogger,
        private readonly PrintLogger $printLogger,
        private readonly CompatibilityReportBuilder $reports,
    ) {}

    /**
     * @throws CaseNotOpen
     * @throws UnitNotInCase
     * @throws UnitNotReserved
     * @throws UnitExpired
     * @throws CrossmatchIncompatible
     * @throws PatientSampleNotCollected
     * @throws AmendmentReasonRequired
     * @throws CaseDonorNotInCase
     * @throws PostDonationVitalsRequired
     * @throws PostDonationVitalsRecorded
     * @throws ValidationException
     */
    public function handle(User $actor, PatientCase $case, IssueBloodData $data): PdfDocument
    {
        return DB::transaction(function () use ($actor, $case, $data): PdfDocument {
            $case = PatientCase::query()->lockForUpdate()->findOrFail($case->id);

            if ($case->status !== CaseStatus::Open) {
                throw new CaseNotOpen;
            }

            $units = $this->units($case, $data);
            $this->ensureCompatible($data);

            $patientSample = $this->issueGate->patientSampleFor($case) ?? throw new PatientSampleNotCollected;

            $this->recordPatientBloodGroup($actor, $case, $patientSample, $data);
            $this->recordPostDonationVitals($actor, $case, $units, $data);
            $crossmatches = $this->recordCrossmatches($actor, $case, $patientSample, $units, $data);
            $this->issueUnits($actor, $units);
            $this->recordSupply($actor, $case, $units, $data);

            foreach ($crossmatches as $crossmatch) {
                $this->printLogger->record(PrintDocumentType::CompatibilityReport, $crossmatch, $actor);
            }

            return $this->reports->build($case, $crossmatches, status: 201);
        });
    }

    /**
     * The requested units, locked and keyed by id, each one reserved for this case and not expired.
     *
     * @return EloquentCollection<int, Unit>
     *
     * @throws UnitNotInCase
     */
    private function units(PatientCase $case, IssueBloodData $data): EloquentCollection
    {
        $units = Unit::query()
            ->whereKey(array_map(fn (CrossmatchUnitData $row): int => $row->unitId, $data->units))
            ->lockForUpdate()
            ->with('collection.caseDonor.screening')
            ->get()
            ->keyBy('id');

        foreach ($data->units as $row) {
            $unit = $units->get($row->unitId) ?? throw new UnitNotInCase;

            if (($refusal = $this->issueGate->refusal($case, $unit)) !== null) {
                throw $refusal;
            }
        }

        return $units;
    }

    /**
     * @throws CrossmatchIncompatible
     */
    private function ensureCompatible(IssueBloodData $data): void
    {
        foreach ($data->units as $row) {
            if (! $row->isCompatible()) {
                throw new CrossmatchIncompatible;
            }
        }
    }

    /**
     * Required while the sample has no blood group; an unchanged value is left alone, a changed one becomes a new
     * version with the amendment reason. The Medical Technologist is the tester.
     *
     * @throws AmendmentReasonRequired
     * @throws ValidationException
     */
    private function recordPatientBloodGroup(User $actor, PatientCase $case, Sample $sample, IssueBloodData $data): void
    {
        $current = $this->issueGate->bloodGroupResult($sample);
        $value = $data->patientBloodGroup?->value;

        if ($current === null && $value === null) {
            throw ValidationException::withMessages(['patient_blood_group' => 'Enter the patient blood group.']);
        }

        if ($value === null || $value === $current?->result_value) {
            return;
        }

        if ($current !== null && blank($data->amendmentReason)) {
            throw new AmendmentReasonRequired;
        }

        $definition = TestDefinition::query()->where('code', SerologyTest::BloodGroup->value)->first()
            ?? throw new LogicException('The BLOOD_GROUP test definition is missing; run the migrations.');

        $current?->update(['is_current' => false]);

        TestResult::query()->create([
            'sample_id' => $sample->id,
            'test_definition_id' => $definition->id,
            'version' => ($current?->version ?? 0) + 1,
            'is_current' => true,
            'result_value' => $value,
            'tested_by' => $data->technologistId,
            'tested_at' => now(),
            'status' => TestResultStatus::Entered,
            'amendment_reason' => $current === null ? null : $data->amendmentReason,
        ]);

        if ($sample->status !== SampleStatus::Tested) {
            $sample->update(['status' => SampleStatus::Tested]);
        }

        $this->auditLogger->record(
            $current === null ? BloodBankAuditAction::PatientBloodGrouped : BloodBankAuditAction::PatientBloodGroupAmended,
            $sample,
            oldValues: $current === null ? [] : ['blood_group' => $current->result_value],
            newValues: [
                'blood_group' => $value,
                'amendment_reason' => $current === null ? null : $data->amendmentReason,
                'case_no' => $case->case_no,
                'sample_no' => $sample->sample_no,
            ],
        );
    }

    /**
     * Every donor whose blood is issued must have post-donation vitals, recorded earlier or sent now. Vitals can
     * be sent for any collected donor of the case still missing them, but never overwrite recorded ones.
     *
     * @param  EloquentCollection<int, Unit>  $units
     *
     * @throws CaseDonorNotInCase
     * @throws PostDonationVitalsRecorded
     * @throws PostDonationVitalsRequired
     * @throws ValidationException
     */
    private function recordPostDonationVitals(User $actor, PatientCase $case, EloquentCollection $units, IssueBloodData $data): void
    {
        $issuedDonors = $units->map(fn (Unit $unit): CaseDonor => $unit->collection->caseDonor)->keyBy('id');
        $entries = collect($data->postDonation)->keyBy(fn (PostDonationVitalsData $entry): int => $entry->caseDonorId);

        $donors = CaseDonor::query()
            ->where('case_id', $case->id)
            ->whereKey($entries->keys()->diff($issuedDonors->keys())->values()->all())
            ->with('screening')
            ->get()
            ->keyBy('id')
            ->union($issuedDonors);

        foreach ($issuedDonors as $caseDonor) {
            if ($this->issueGate->needsPostVitals($caseDonor) && ! $entries->has($caseDonor->id)) {
                throw new PostDonationVitalsRequired;
            }
        }

        foreach ($data->postDonation as $index => $entry) {
            $caseDonor = $donors->get($entry->caseDonorId) ?? throw new CaseDonorNotInCase;

            if (! $this->issueGate->needsPostVitals($caseDonor)) {
                throw $caseDonor->screening?->post_recorded_at !== null
                    ? new PostDonationVitalsRecorded
                    : ValidationException::withMessages(["post_donation.{$index}.case_donor_id" => 'Post-donation vitals can only be recorded for a donor whose blood was collected.']);
            }

            $caseDonor->screening->update([
                'post_pulse_bpm' => $entry->pulseBpm,
                'post_bp_systolic' => $entry->bpSystolic,
                'post_bp_diastolic' => $entry->bpDiastolic,
                'post_temperature_f' => $entry->temperatureF,
                'post_recorded_by' => $actor->id,
                'post_recorded_at' => now(),
            ]);

            $this->auditLogger->record(BloodBankAuditAction::DonorPostVitalsRecorded, $caseDonor, newValues: [
                'pulse_bpm' => $entry->pulseBpm,
                'bp_systolic' => $entry->bpSystolic,
                'bp_diastolic' => $entry->bpDiastolic,
                'temperature_f' => $entry->temperatureF,
                'case_no' => $case->case_no,
            ]);
        }
    }

    /**
     * One crossmatch row per unit. A unit crossmatched before gets a new version, which needs the amendment reason.
     *
     * @param  EloquentCollection<int, Unit>  $units
     * @return EloquentCollection<int, Crossmatch> in request order
     *
     * @throws AmendmentReasonRequired
     */
    private function recordCrossmatches(User $actor, PatientCase $case, Sample $patientSample, EloquentCollection $units, IssueBloodData $data): EloquentCollection
    {
        $crossmatches = new EloquentCollection;

        foreach ($data->units as $row) {
            $unit = $units[$row->unitId];
            $previous = Crossmatch::query()->where('unit_id', $unit->id)->where('case_id', $case->id)->current()->lockForUpdate()->get();

            if ($previous->isNotEmpty() && blank($data->amendmentReason)) {
                throw new AmendmentReasonRequired;
            }

            Crossmatch::query()->whereKey($previous->modelKeys())->update(['is_current' => false]);

            $version = (int) Crossmatch::query()->where('patient_sample_id', $patientSample->id)->where('unit_id', $unit->id)->max('version');

            $crossmatch = Crossmatch::query()->create([
                'case_id' => $case->id,
                'patient_sample_id' => $patientSample->id,
                'unit_id' => $unit->id,
                'version' => $version + 1,
                'is_current' => true,
                'major_result' => $row->majorResult,
                'minor_result' => $row->minorResult,
                'remarks' => $row->remarks,
                'tested_by' => $data->technologistId,
                'tested_at' => now(),
                'status' => TestResultStatus::Reviewed,
                'reviewed_by' => $data->physicianId,
                'reviewed_at' => now(),
                'amendment_reason' => $previous->isEmpty() ? null : $data->amendmentReason,
            ]);

            $this->auditLogger->record(BloodBankAuditAction::CrossmatchRecorded, $crossmatch, newValues: [
                'case_no' => $case->case_no,
                'unit_no' => $unit->unit_no,
                'sample_no' => $patientSample->sample_no,
                'version' => $crossmatch->version,
                'major_result' => $row->majorResult->value,
                'minor_result' => $row->minorResult->value,
                'remarks' => $row->remarks,
                'tested_by' => $data->technologistId,
                'reviewed_by' => $data->physicianId,
            ]);

            $crossmatches->push($crossmatch);
        }

        return $crossmatches;
    }

    /**
     * An issued unit stays linked to the case and leaves its storage location.
     *
     * @param  EloquentCollection<int, Unit>  $units
     */
    private function issueUnits(User $actor, EloquentCollection $units): void
    {
        foreach ($units as $unit) {
            $fromLocationId = $unit->storage_location_id;

            $unit->update(['status' => UnitStatus::Issued, 'storage_location_id' => null]);

            $this->movements->record($unit, $actor, UnitStatus::Reserved, $fromLocationId, 'Issued to the patient');
        }
    }

    /**
     * The supply time is the latest issue; the case closes once nothing billed is left to issue.
     *
     * @param  EloquentCollection<int, Unit>  $units
     */
    private function recordSupply(User $actor, PatientCase $case, EloquentCollection $units, IssueBloodData $data): void
    {
        $case->update(['supplied_at' => now()]);

        $this->auditLogger->record(BloodBankAuditAction::BloodIssued, $case, newValues: [
            'case_no' => $case->case_no,
            'unit_nos' => $units->pluck('unit_no')->values()->all(),
            'technologist_id' => $data->technologistId,
            'physician_id' => $data->physicianId,
            'supplied_at' => $case->supplied_at,
        ]);

        if ($this->issueGate->remainingCounts($case) !== []) {
            return;
        }

        $case->update(['status' => CaseStatus::Closed]);

        $this->auditLogger->record(BloodBankAuditAction::CaseClosed, $case, oldValues: ['status' => CaseStatus::Open->value], newValues: [
            'status' => CaseStatus::Closed->value,
            'case_no' => $case->case_no,
        ]);
    }
}
