<?php

namespace Modules\Shared\Branch\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Shared\Audit\Contracts\AuditLogger;
use Modules\Shared\Audit\Enums\AuditAction;
use Modules\Shared\Branch\Data\BranchData;
use Modules\Shared\Branch\Models\Branch;
use Modules\Shared\Branch\Services\BranchModuleWriter;
use Spatie\LaravelData\Optional;

final class CreateBranch
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly BranchModuleWriter $branchModuleWriter,
    ) {}

    public function handle(BranchData $data): Branch
    {
        return DB::transaction(function () use ($data): Branch {
            $branch = Branch::create($data->branchColumns());
            $modules = $data->modules instanceof Optional ? [] : $data->modules;
            $this->branchModuleWriter->replace($branch, $modules);

            $this->auditLogger->record(AuditAction::BranchCreated, $branch, newValues: [...$data->branchColumns(), 'modules' => $this->branchModuleWriter->sorted($modules)]);

            return $branch->load('modules');
        });
    }
}
