<?php

namespace Modules\BloodBank\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\BloodBank\Data\CollectionSubject;
use Modules\BloodBank\Data\CollectSamplesData;
use Modules\BloodBank\Data\SampleCollectionView;
use Modules\BloodBank\Data\SampleSubjectData;
use Modules\BloodBank\Enums\BloodBankAuditAction;
use Modules\BloodBank\Enums\CaseDonorStatus;
use Modules\BloodBank\Enums\CaseStatus;
use Modules\BloodBank\Enums\SampleStatus;
use Modules\BloodBank\Enums\SampleSubject;
use Modules\BloodBank\Exceptions\CaseDonorNotInCase;
use Modules\BloodBank\Exceptions\CaseNotOpen;
use Modules\BloodBank\Exceptions\DonorDeferred;
use Modules\BloodBank\Exceptions\SampleAlreadyCollected;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Models\Sample;
use Modules\BloodBank\Queries\ShowSampleCollection;
use Modules\BloodBank\Services\CaseSampleSubjects;
use Modules\BloodBank\Services\SampleNumber;
use Modules\Shared\Audit\Contracts\AuditLogger;
use Modules\Shared\Auth\Models\User;

/**
 * Collects one sample per ticked row of the collection screen (the patient and/or donors of the case), each
 * with its own sample no. All or nothing: one row that cannot be collected rejects the whole request. The case
 * row is locked so a double click cannot draw two samples for the same subject.
 */
final class CollectSamples
{
    public function __construct(
        private readonly CaseSampleSubjects $sampleSubjects,
        private readonly SampleNumber $sampleNumber,
        private readonly AuditLogger $auditLogger,
        private readonly ShowSampleCollection $showSampleCollection,
    ) {}

    /**
     * @throws CaseNotOpen
     * @throws CaseDonorNotInCase
     * @throws DonorDeferred
     * @throws SampleAlreadyCollected
     * @throws ValidationException
     */
    public function handle(User $actor, PatientCase $case, CollectSamplesData $data): SampleCollectionView
    {
        $this->ensurePatientListedOnce($data);
        $collectedSampleIds = [];

        $case = DB::transaction(function () use ($actor, $case, $data, &$collectedSampleIds): PatientCase {
            $case = PatientCase::query()->lockForUpdate()->findOrFail($case->id);

            if ($case->status !== CaseStatus::Open) {
                throw new CaseNotOpen;
            }

            $subjects = $this->sampleSubjects->of($case);
            $collectedAt = $data->collectedAt ?? now();

            foreach ($data->subjects as $requested) {
                $this->ensureCollectable($this->find($subjects, $requested));

                $sample = Sample::create([
                    'sample_no' => $this->sampleNumber->next(),
                    'case_id' => $case->id,
                    'case_donor_id' => $requested->caseDonorId,
                    'subject_type' => $requested->subjectType,
                    'collected_at' => $collectedAt,
                    'collected_by' => $actor->id,
                    'status' => SampleStatus::Collected,
                ]);

                $this->auditLogger->record(BloodBankAuditAction::SampleCollected, $sample, newValues: [
                    ...$sample->only(['sample_no', 'case_id', 'case_donor_id', 'subject_type', 'collected_at']),
                    'case_no' => $case->case_no,
                ]);

                $collectedSampleIds[] = $sample->id;
            }

            return $case->setRelations([]);
        });

        return $this->showSampleCollection->get($case, $collectedSampleIds);
    }

    /**
     * @throws ValidationException
     */
    private function ensurePatientListedOnce(CollectSamplesData $data): void
    {
        $patientRows = array_filter($data->subjects, fn (SampleSubjectData $subject): bool => $subject->subjectType === SampleSubject::Patient);

        if (count($patientRows) > 1) {
            throw ValidationException::withMessages(['subjects' => 'The patient may only be listed once.']);
        }
    }

    /**
     * @param  list<CollectionSubject>  $subjects
     *
     * @throws CaseDonorNotInCase
     */
    private function find(array $subjects, SampleSubjectData $requested): CollectionSubject
    {
        foreach ($subjects as $subject) {
            if ($subject->subject->type === $requested->subjectType && $subject->subject->caseDonor?->id === $requested->caseDonorId) {
                return $subject;
            }
        }

        throw new CaseDonorNotInCase;
    }

    /**
     * @throws DonorDeferred
     * @throws SampleAlreadyCollected
     */
    private function ensureCollectable(CollectionSubject $subject): void
    {
        if ($subject->subject->caseDonor?->status === CaseDonorStatus::Deferred) {
            throw new DonorDeferred;
        }

        if (! $subject->isCollectable) {
            throw new SampleAlreadyCollected;
        }
    }
}
