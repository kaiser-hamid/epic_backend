<?php

namespace Modules\BloodBank\Services;

use Modules\BloodBank\Enums\CaseDonorStatus;
use Modules\BloodBank\Enums\CaseStatus;
use Modules\BloodBank\Enums\SampleStatus;
use Modules\BloodBank\Enums\SampleSubject;
use Modules\BloodBank\Enums\SerologyTest;
use Modules\BloodBank\Enums\UnitStatus;
use Modules\BloodBank\Exceptions\CaseNotOpen;
use Modules\BloodBank\Exceptions\UnitExpired;
use Modules\BloodBank\Exceptions\UnitNotInCase;
use Modules\BloodBank\Exceptions\UnitNotReserved;
use Modules\BloodBank\Models\CaseDonor;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Models\Sample;
use Modules\BloodBank\Models\TestResult;
use Modules\BloodBank\Models\Unit;
use Modules\Shared\Support\Exceptions\DomainException;

/**
 * Whether a unit can be issued to a case's patient (the case is open, the unit is reserved for it and not
 * expired), the patient sample the crossmatch is done on, which donors have no post-donation vitals yet, and how
 * much of what the case was billed for is issued.
 */
final class BloodIssueGate
{
    /**
     * The reason the unit cannot be issued to this case now, or null when it can. The crossmatch and post-donation
     * vitals come with the issue itself, so they are not checked here.
     */
    public function refusal(PatientCase $case, Unit $unit): ?DomainException
    {
        return match (true) {
            $case->status !== CaseStatus::Open => new CaseNotOpen,
            $unit->case_id !== $case->id => new UnitNotInCase,
            $unit->status !== UnitStatus::Reserved => new UnitNotReserved,
            $unit->expires_at->isPast() => new UnitExpired,
            default => null,
        };
    }

    /**
     * The latest patient sample of the case that was not rejected, with `results.testDefinition` loaded.
     */
    public function patientSampleFor(PatientCase $case): ?Sample
    {
        return $case->samples()
            ->where('subject_type', SampleSubject::Patient)
            ->where('status', '!=', SampleStatus::Rejected)
            ->with('results.testDefinition')
            ->latest('id')
            ->first();
    }

    /**
     * The current blood group result on the patient sample.
     */
    public function bloodGroupResult(?Sample $sample): ?TestResult
    {
        return $sample?->results->first(fn (TestResult $result): bool => $result->is_current
            && $result->testDefinition->code === SerologyTest::BloodGroup->value);
    }

    /**
     * A collected donor whose examination has no post-donation vitals yet (`screening` loaded).
     */
    public function needsPostVitals(CaseDonor $caseDonor): bool
    {
        return $caseDonor->status === CaseDonorStatus::Collected
            && $caseDonor->screening !== null
            && $caseDonor->screening->post_recorded_at === null;
    }

    /**
     * Units issued to the case, per component type id.
     *
     * @return array<int, int>
     */
    public function issuedCounts(PatientCase $case): array
    {
        return $case->units()
            ->where('status', UnitStatus::Issued)
            ->selectRaw('component_type_id, count(*) as aggregate')
            ->groupBy('component_type_id')
            ->pluck('aggregate', 'component_type_id')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }

    /**
     * Units the case was billed for and has not been issued yet, per component type id; only types still due.
     * An empty result means the case is fully supplied.
     *
     * @return array<int, int>
     */
    public function remainingCounts(PatientCase $case): array
    {
        $issued = $this->issuedCounts($case);

        return $case->components()
            ->pluck('quantity', 'component_type_id')
            ->map(fn (mixed $quantity, int $componentTypeId): int => (int) $quantity - ($issued[$componentTypeId] ?? 0))
            ->filter(fn (int $count): bool => $count > 0)
            ->all();
    }
}
