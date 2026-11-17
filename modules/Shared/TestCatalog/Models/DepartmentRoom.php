<?php

namespace Modules\Shared\TestCatalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Shared\Branch\Models\Branch;

/**
 * The room a non-lab department receives patients in at one branch, printed on the bill's Non-Lab Copy.
 */
#[Table('sh_department_rooms')]
#[Fillable(['branch_id', 'department_id', 'room_no'])]
final class DepartmentRoom extends Model
{
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }
}
