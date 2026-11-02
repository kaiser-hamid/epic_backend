<?php

namespace Modules\BloodBank\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;
use Modules\BloodBank\Data\DonorSerologyView;
use Modules\BloodBank\Data\RecordDonorSerologyData;
use Modules\BloodBank\Enums\BloodBankAuditAction;
use Modules\BloodBank\Enums\CaseDonorStatus;
use Modules\BloodBank\Enums\CaseStatus;
use Modules\BloodBank\Enums\SampleStatus;
use Modules\BloodBank\Enums\SerologyDecision;
use Modules\BloodBank\Enums\SerologyTest;
use Modules\BloodBank\Enums\TestResultStatus;
use Modules\BloodBank\Exceptions\AmendmentReasonRequired;
use Modules\BloodBank\Exceptions\CaseDonorNotInCase;
use Modules\BloodBank\Exceptions\CaseNotOpen;
use Modules\BloodBank\Exceptions\ReactiveDonorNotAcceptable;
use Modules\BloodBank\Models\CaseDonor;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Models\Sample;
use Modules\BloodBank\Models\TestDefinition;
use Modules\BloodBank\Models\TestResult;
use Modules\BloodBank\Queries\ShowDonorSerology;
use Modules\BloodBank\Services\DonorSerologyGate;
use Modules\Shared\Audit\Contracts\AuditLogger;
use Modules\Shared\Auth\Models\User;

/**
 * Saves a donor's serological investigation on the donor sample, with the physician's required Accept
 * (`eligible`) / Defer. Results are versioned: a changed value gets a new version with the amendment reason and
 * the old one stays as history; an unchanged value is left alone. The blood group also becomes the donor's
 * blood group. A donor with a reactive screening result can only be deferred.
 */
final class RecordDonorSerology
{
    public function __construct(
        private readonly DonorSerologyGate $serologyGate,
        private readonly AuditLogger $auditLogger,
        private readonly ShowDonorSerology $showDonorSerology,
    ) {}

    /**
     * @throws CaseNotOpen
     * @throws CaseDonorNotInCase
     * @throws ReactiveDonorNotAcceptable
     * @throws AmendmentReasonRequired
     */
    public function handle(User $actor, PatientCase $case, CaseDonor $caseDonor, RecordDonorSerologyData $data): DonorSerologyView
    {
        $case = DB::transaction(function () use ($actor, $case, $caseDonor, $data): PatientCase {
            $case = PatientCase::query()->lockForUpdate()->findOrFail($case->id);

            if ($case->status !== CaseStatus::Open) {
                throw new CaseNotOpen;
            }

            if ($caseDonor->case_id !== $case->id) {
                throw new CaseDonorNotInCase;
            }

            $caseDonor = CaseDonor::query()->with('donor')->lockForUpdate()->findOrFail($caseDonor->id);

            if (($refusal = $this->serologyGate->refusal($caseDonor)) !== null) {
                throw $refusal;
            }

            if ($data->decision === SerologyDecision::Accepted && $data->hasReactiveResult()) {
                throw new ReactiveDonorNotAcceptable;
            }

            /** @var Sample $sample */
            $sample = $this->serologyGate->sampleFor($caseDonor);
            $definitions = $this->definitions();
            $currentResults = $sample->results->where('is_current', true)->keyBy('test_definition_id');
            $isAmendment = $currentResults->isNotEmpty();
            $oldValues = $isAmendment ? $this->snapshot($caseDonor, $currentResults, $definitions) : [];

            $this->saveResults($actor, $sample, $data, $definitions, $currentResults);

            if ($sample->status !== SampleStatus::Tested) {
                $sample->update(['status' => SampleStatus::Tested]);
            }

            $caseDonor->donor->update(['blood_group' => $data->bloodGroup]);
            $this->recordDecision($actor, $caseDonor, $data);

            $this->auditLogger->record(
                $isAmendment ? BloodBankAuditAction::DonorSerologyAmended : BloodBankAuditAction::DonorSerologyRecorded,
                $caseDonor,
                oldValues: $oldValues,
                newValues: [
                    ...$this->snapshot($caseDonor, $sample->results()->current()->get()->keyBy('test_definition_id'), $definitions),
                    'amendment_reason' => $isAmendment ? $data->amendmentReason : null,
                    'case_no' => $case->case_no,
                    'sample_no' => $sample->sample_no,
                ],
            );

            return $case->setRelations([]);
        });

        return $this->showDonorSerology->get($case);
    }

    /**
     * The serology test definitions keyed by code. They are inserted by a migration, so a missing one is a
     * deployment fault (500), not a "not found" for the client.
     *
     * @return array<string, TestDefinition>
     */
    private function definitions(): array
    {
        $definitions = TestDefinition::query()
            ->whereIn('code', array_column(SerologyTest::cases(), 'value'))
            ->get()
            ->keyBy('code');

        $byCode = [];

        foreach (SerologyTest::cases() as $test) {
            $byCode[$test->value] = $definitions->get($test->value)
                ?? throw new LogicException("Serology test definition {$test->value} is missing; run the migrations.");
        }

        return $byCode;
    }

    /**
     * @param  array<string, TestDefinition>  $definitions
     * @param  Collection<int, TestResult>  $currentResults  keyed by test definition id
     *
     * @throws AmendmentReasonRequired
     */
    private function saveResults(User $actor, Sample $sample, RecordDonorSerologyData $data, array $definitions, Collection $currentResults): void
    {
        $changes = [];

        foreach ($data->results() as $code => $value) {
            $current = $currentResults->get($definitions[$code]->id);

            if ($current?->result_value !== $value) {
                $changes[] = [$definitions[$code], $current, $value];
            }
        }

        $isCorrection = collect($changes)->contains(fn (array $change): bool => $change[1] !== null);

        if ($isCorrection && blank($data->amendmentReason)) {
            throw new AmendmentReasonRequired;
        }

        foreach ($changes as [$definition, $current, $value]) {
            $current?->update(['is_current' => false]);

            TestResult::query()->create([
                'sample_id' => $sample->id,
                'test_definition_id' => $definition->id,
                'version' => ($current?->version ?? 0) + 1,
                'is_current' => true,
                'result_value' => $value,
                'tested_by' => $actor->id,
                'tested_at' => now(),
                'status' => TestResultStatus::Entered,
                'amendment_reason' => $current === null ? null : $data->amendmentReason,
            ]);
        }
    }

    /**
     * The assessor and time change only when the decision itself changes, so re-saving a corrected result does
     * not re-sign an earlier decision.
     */
    private function recordDecision(User $actor, CaseDonor $caseDonor, RecordDonorSerologyData $data): void
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
     * @param  Collection<int, TestResult>  $results  current results keyed by test definition id
     * @param  array<string, TestDefinition>  $definitions
     * @return array<string, mixed>
     */
    private function snapshot(CaseDonor $caseDonor, Collection $results, array $definitions): array
    {
        $values = [];

        foreach (SerologyTest::cases() as $test) {
            $values[$test->field()] = $results->get($definitions[$test->value]->id)?->result_value;
        }

        return [
            ...$values,
            'status' => $caseDonor->status->value,
            'deferral_reason' => $caseDonor->deferral_reason,
        ];
    }
}
