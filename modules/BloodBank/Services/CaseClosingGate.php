<?php

namespace Modules\BloodBank\Services;

use Modules\BloodBank\Enums\CaseStatus;
use Modules\BloodBank\Enums\UnitStatus;
use Modules\BloodBank\Exceptions\CaseHasReservedUnits;
use Modules\BloodBank\Exceptions\CaseNotOpen;
use Modules\BloodBank\Models\PatientCase;
use Modules\Shared\Support\Exceptions\DomainException;

/**
 * Whether a case can be closed by hand ("Close case" on the case details page), and whether that needs a reason.
 * A case with units still reserved for it cannot close (they have to go back to stock first). Closing needs a
 * reason while billed work is outstanding: components not yet issued, or a procedure that was never done
 * (procedure cases close by themselves when done). A case with nothing outstanding (only services such as cross
 * matching or a bed charge) closes without one.
 */
final class CaseClosingGate
{
    public function __construct(private readonly BloodIssueGate $issueGate) {}

    /**
     * The reason the case cannot be closed now, or null when it can.
     */
    public function refusal(PatientCase $case): ?DomainException
    {
        return match (true) {
            $case->status !== CaseStatus::Open => new CaseNotOpen,
            $case->units()->where('status', UnitStatus::Reserved)->exists() => new CaseHasReservedUnits,
            default => null,
        };
    }

    public function needsReason(PatientCase $case): bool
    {
        return $case->service->isProcedure() || $this->outstandingComponents($case) !== [];
    }

    /**
     * Billed units not issued yet, per component type id.
     *
     * @return array<int, int>
     */
    public function outstandingComponents(PatientCase $case): array
    {
        return $this->issueGate->remainingCounts($case);
    }
}
