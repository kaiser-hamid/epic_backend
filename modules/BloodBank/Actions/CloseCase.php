<?php

namespace Modules\BloodBank\Actions;

use Illuminate\Support\Facades\DB;
use Modules\BloodBank\Data\CaseView;
use Modules\BloodBank\Data\CloseCaseData;
use Modules\BloodBank\Exceptions\CaseHasReservedUnits;
use Modules\BloodBank\Exceptions\CaseNotOpen;
use Modules\BloodBank\Exceptions\CloseReasonRequired;
use Modules\BloodBank\Models\ComponentType;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Queries\ShowCase;
use Modules\BloodBank\Services\CaseCloser;
use Modules\BloodBank\Services\CaseClosingGate;
use Modules\Shared\Auth\Models\User;

/**
 * "Close case" on the case details page, for a case that does not close by itself: a bill with only services
 * (cross matching, bed charge, infusion), or a case that cannot finish (the patient left, got fewer units than
 * billed, no replacement donor came). Refused while units are reserved for the case. A reason is required while
 * billed work is outstanding, and the audit log records what was not supplied.
 */
final class CloseCase
{
    public function __construct(
        private readonly CaseClosingGate $closingGate,
        private readonly CaseCloser $caseCloser,
        private readonly ShowCase $showCase,
    ) {}

    /**
     * @throws CaseNotOpen
     * @throws CaseHasReservedUnits
     * @throws CloseReasonRequired
     */
    public function handle(User $actor, PatientCase $case, CloseCaseData $data): CaseView
    {
        $case = DB::transaction(function () use ($actor, $case, $data): PatientCase {
            $case = PatientCase::query()->lockForUpdate()->findOrFail($case->id);

            if (($refusal = $this->closingGate->refusal($case)) !== null) {
                throw $refusal;
            }

            $reason = blank($data->reason) ? null : trim((string) $data->reason);

            if ($reason === null && $this->closingGate->needsReason($case)) {
                throw new CloseReasonRequired;
            }

            $this->caseCloser->close($case, $actor, 'close_case', $reason, [
                'outstanding' => $this->outstanding($case),
            ]);

            return $case;
        });

        return $this->showCase->get($case);
    }

    /**
     * Units billed but not issued, by component code (e.g. `['PCV' => 1]`).
     *
     * @return array<string, int>
     */
    private function outstanding(PatientCase $case): array
    {
        $counts = $this->closingGate->outstandingComponents($case);
        $codes = ComponentType::query()->whereKey(array_keys($counts))->pluck('code', 'id');

        $outstanding = [];
        foreach ($counts as $componentTypeId => $count) {
            $outstanding[(string) ($codes[$componentTypeId] ?? $componentTypeId)] = $count;
        }

        return $outstanding;
    }
}
