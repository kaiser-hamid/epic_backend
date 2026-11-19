<?php

namespace Modules\BloodBank\Services;

use Modules\BloodBank\Data\CollectionSubject;
use Modules\BloodBank\Data\SubjectView;
use Modules\BloodBank\Enums\CaseDonorStatus;
use Modules\BloodBank\Enums\CaseStatus;
use Modules\BloodBank\Enums\SampleStatus;
use Modules\BloodBank\Enums\SampleSubject;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Models\Sample;
use Modules\Shared\Patient\Data\PatientSummary;

/**
 * Who a case's samples are drawn from: the patient, then every case donor in sequence order, each with its
 * latest sample. A subject is collectable while the case is open, it has no sample or only rejected ones, and
 * (for a donor) the physician accepted the donor at the physical exam (`fit`). A Therapeutic Phlebotomy case
 * takes no patient sample.
 */
final class CaseSampleSubjects
{
    /**
     * @return list<CollectionSubject>
     */
    public function of(PatientCase $case, ?PatientSummary $patient = null): array
    {
        $case->loadMissing(['samples', 'caseDonors.donor']);

        $subjects = [$this->subject($case, new SubjectView(SampleSubject::Patient, patient: $patient))];

        foreach ($case->caseDonors->sortBy('sequence_no') as $caseDonor) {
            $subjects[] = $this->subject($case, new SubjectView(SampleSubject::Donor, caseDonor: $caseDonor));
        }

        return $subjects;
    }

    private function subject(PatientCase $case, SubjectView $subject): CollectionSubject
    {
        $sample = $case->samples
            ->filter(fn (Sample $sample): bool => $sample->subject_type === $subject->type
                && $sample->case_donor_id === $subject->caseDonor?->id)
            ->sortByDesc('id')
            ->first();

        return new CollectionSubject(
            $subject,
            $sample,
            $case->status === CaseStatus::Open
                && ($sample === null || $sample->status === SampleStatus::Rejected)
                && $this->isReadyForSample($case, $subject),
        );
    }

    /**
     * The patient is, unless the case takes no sample (Therapeutic Phlebotomy); a donor only once the physician
     * accepted them at the physical exam (`fit`).
     */
    private function isReadyForSample(PatientCase $case, SubjectView $subject): bool
    {
        return $subject->type === SampleSubject::Patient
            ? $case->service->takesPatientSample()
            : $subject->caseDonor?->status === CaseDonorStatus::Fit;
    }
}
