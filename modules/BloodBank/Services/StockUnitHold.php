<?php

namespace Modules\BloodBank\Services;

use Modules\BloodBank\Enums\BloodBankAuditAction;
use Modules\BloodBank\Enums\UnitStatus;
use Modules\BloodBank\Models\PatientCase;
use Modules\BloodBank\Models\Unit;
use Modules\Shared\Audit\Contracts\AuditLogger;
use Modules\Shared\Auth\Models\User;

/**
 * Moves a stock unit taken for a case through its statuses, each with a movement row and an audit entry:
 * `available` → `held` (bag sample drawn for the case), `held` → `reserved` (patient label printed), and `held` →
 * `available` / `quarantined` (deferred at serology, or the case closed). The caller holds the unit row lock and
 * has checked the move is allowed. A unit keeps its storage location throughout: it stays in its fridge until
 * issued.
 */
final class StockUnitHold
{
    public function __construct(
        private readonly UnitMovementRecorder $movements,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function hold(User $actor, PatientCase $case, Unit $unit): void
    {
        $from = $unit->status;

        $unit->update(['status' => UnitStatus::Held, 'case_id' => $case->id]);

        $this->movements->record($unit, $actor, $from, $unit->storage_location_id, "Held for case {$case->case_no} (sample taken)");
        $this->audit(BloodBankAuditAction::StockUnitHeld, $unit, $from, $case);
    }

    /**
     * @param  int|null  $storageLocationId  a new fridge for the unit, else it stays where it is
     */
    public function reserve(User $actor, PatientCase $case, Unit $unit, ?int $storageLocationId = null): void
    {
        $from = $unit->status;
        $fromLocationId = $unit->storage_location_id;

        $unit->update([
            'status' => UnitStatus::Reserved,
            'storage_location_id' => $storageLocationId ?? $fromLocationId,
        ]);

        $this->movements->record($unit, $actor, $from, $fromLocationId, "Reserved for case {$case->case_no} (patient label printed)");
        $this->audit(BloodBankAuditAction::StockUnitReserved, $unit, $from, $case);
    }

    /**
     * Back to stock (`available`), or `quarantined` when it must not be given to anyone until a decision is made.
     * The case link is cleared; the movement row keeps it.
     */
    public function release(User $actor, PatientCase $case, Unit $unit, string $reason, bool $quarantine = false): void
    {
        $from = $unit->status;

        $unit->update(['status' => $quarantine ? UnitStatus::Quarantined : UnitStatus::Available, 'case_id' => null]);

        $this->movements->record($unit, $actor, $from, $unit->storage_location_id, $reason, caseId: $case->id);

        $this->audit(BloodBankAuditAction::StockUnitReleased, $unit, $from, $case, ['reason' => $reason]);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function audit(BloodBankAuditAction $action, Unit $unit, UnitStatus $from, PatientCase $case, array $details = []): void
    {
        $this->auditLogger->record($action, $unit, oldValues: ['status' => $from->value], newValues: [
            'status' => $unit->status->value,
            'unit_no' => $unit->unit_no,
            'case_no' => $case->case_no,
            ...$details,
        ]);
    }
}
