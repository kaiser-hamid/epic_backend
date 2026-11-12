<?php

namespace Modules\Shared\Branch\Actions;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Modules\Shared\Audit\Contracts\AuditLogger;
use Modules\Shared\Audit\Enums\AuditAction;
use Modules\Shared\Branch\Data\BranchData;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Branch\Services\BranchModuleWriter;
use Spatie\LaravelData\Optional;

/**
 * Edits a branch. Deactivating it ends every session working in it on their next request. Switching an
 * optional module off makes its permissions in this branch allow nothing at once; the grants themselves stay
 * until an admin next saves the user's permissions there.
 */
final class UpdateBranch
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly BranchModuleWriter $branchModuleWriter,
    ) {}

    public function handle(Branch $branch, BranchData $data): Branch
    {
        return DB::transaction(function () use ($branch, $data): Branch {
            $branch->fill($data->branchColumns());
            $changes = $branch->getDirty();
            $original = Arr::only($branch->getOriginal(), array_keys($changes));

            if ($changes !== []) {
                $branch->save();
            }

            if (! $data->modules instanceof Optional) {
                $oldModules = $this->branchModuleWriter->sorted($branch->modules()->pluck('module')->all());
                $newModules = $this->branchModuleWriter->sorted($data->modules);

                if ($oldModules !== $newModules) {
                    $this->branchModuleWriter->replace($branch, $newModules);
                    $original['modules'] = $oldModules;
                    $changes['modules'] = $newModules;
                }
            }

            if ($changes !== []) {
                $this->auditLogger->record(AuditAction::BranchUpdated, $branch, $original, $changes);
            }

            return $branch->load('modules');
        });
    }
}
