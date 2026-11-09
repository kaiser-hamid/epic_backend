<?php

namespace Modules\BloodBank\Actions;

use Illuminate\Support\Facades\DB;
use Modules\BloodBank\Data\CaseView;
use Modules\BloodBank\Data\UpdateCaseDetailsData;
use Modules\BloodBank\Enums\BloodBankAuditAction;
use Modules\BloodBank\Enums\CaseStatus;
use Modules\BloodBank\Exceptions\CaseNotOpen;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Queries\ShowCase;
use Modules\Shared\Audit\Contracts\AuditLogger;
use Spatie\LaravelData\Optional;

/**
 * Edits an open case's own details ("Care of", cause of transfusion, note). The bill is never touched. The audit
 * log keeps the old and new values of what changed; a save that changes nothing is not logged.
 */
final class UpdateCaseDetails
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly ShowCase $showCase,
    ) {}

    /**
     * @throws CaseNotOpen
     */
    public function handle(PatientCase $case, UpdateCaseDetailsData $data): CaseView
    {
        $case = DB::transaction(function () use ($case, $data): PatientCase {
            $case = PatientCase::query()->lockForUpdate()->findOrFail($case->id);

            if ($case->status !== CaseStatus::Open) {
                throw new CaseNotOpen;
            }

            $case->fill(array_filter([
                'care_type' => $data->careType,
                'transfusion_reason' => $data->transfusionReason,
                'reference_note' => $data->referenceNote,
            ], fn (mixed $value): bool => ! $value instanceof Optional));

            $changed = array_keys($case->getDirty());

            if ($changed !== []) {
                $oldValues = array_intersect_key($case->getOriginal(), array_flip($changed));
                $case->save();

                $this->auditLogger->record(BloodBankAuditAction::CaseDetailsUpdated, $case, oldValues: $oldValues, newValues: $case->only($changed));
            }

            return $case;
        });

        return $this->showCase->get($case);
    }
}
