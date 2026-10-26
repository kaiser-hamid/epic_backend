<?php

namespace Modules\Shared\Branch\Actions;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Modules\Shared\Audit\Contracts\AuditLogger;
use Modules\Shared\Audit\Enums\AuditAction;
use Modules\Shared\Branch\Data\BranchData;
use Modules\Shared\Branch\Models\Branch;

/**
 * Edits a branch. Deactivating it ends every session working in it on their next request.
 */
final class UpdateBranch
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(Branch $branch, BranchData $data): Branch
    {
        return DB::transaction(function () use ($branch, $data): Branch {
            $branch->fill($data->toArray());
            $changes = $branch->getDirty();
            $original = Arr::only($branch->getOriginal(), array_keys($changes));

            if ($changes !== []) {
                $branch->save();
                $this->auditLogger->record(AuditAction::BranchUpdated, $branch, $original, $changes);
            }

            return $branch;
        });
    }
}
