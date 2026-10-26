<?php

namespace Modules\Shared\Branch\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Shared\Audit\Contracts\AuditLogger;
use Modules\Shared\Audit\Enums\AuditAction;
use Modules\Shared\Branch\Data\BranchData;
use Modules\Shared\Branch\Models\Branch;

final class CreateBranch
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(BranchData $data): Branch
    {
        return DB::transaction(function () use ($data): Branch {
            $branch = Branch::create($data->toArray());

            $this->auditLogger->record(AuditAction::BranchCreated, $branch, newValues: $data->toArray());

            return $branch;
        });
    }
}
