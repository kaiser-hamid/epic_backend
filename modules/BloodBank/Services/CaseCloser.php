<?php

namespace Modules\BloodBank\Services;

use Modules\BloodBank\Enums\BloodBankAuditAction;
use Modules\BloodBank\Enums\CaseStatus;
use Modules\BloodBank\Models\PatientCase;
use Modules\Shared\Audit\Contracts\AuditLogger;
use Modules\Shared\Auth\Models\User;

/**
 * Closes an open case (`closed`, `closed_at`, `closed_by`, optional `close_reason`) and audit-logs it, for every
 * step that ends a case: the last issue (Blood Issue), the Autologous PRP sample, the therapeutic phlebotomy and
 * "Close case". The caller holds the case row lock and has checked that it may close.
 */
final class CaseCloser
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  string  $via  the step that closed it, kept in the audit log (`blood_issue`, `sample_collection`, …)
     * @param  array<string, mixed>  $details  extra audit values (e.g. what was still outstanding)
     */
    public function close(PatientCase $case, User $actor, string $via, ?string $reason = null, array $details = []): void
    {
        $case->update([
            'status' => CaseStatus::Closed,
            'closed_at' => now(),
            'closed_by' => $actor->id,
            'close_reason' => $reason,
        ]);

        $this->auditLogger->record(BloodBankAuditAction::CaseClosed, $case, oldValues: ['status' => CaseStatus::Open->value], newValues: [
            'status' => CaseStatus::Closed->value,
            'case_no' => $case->case_no,
            'via' => $via,
            'close_reason' => $reason,
            ...$details,
        ]);
    }
}
