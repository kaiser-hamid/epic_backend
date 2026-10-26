<?php

namespace Modules\Shared\Auth\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Modules\Shared\Auth\Models\User;

/**
 * Filters: `filter[search]` (name, username, employee id), `filter[is_active]`, `filter[branch_id]`
 * (users holding any permission there). Sorted by name. `per_page` up to 100.
 */
final class ListUsers
{
    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function get(Request $request): LengthAwarePaginator
    {
        $search = $request->string('filter.search')->trim()->toString();
        $isActive = $request->input('filter.is_active');
        $branchId = $request->integer('filter.branch_id');

        return User::query()
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereLike('name', "%{$search}%")
                ->orWhereLike('username', "%{$search}%")
                ->orWhereLike('employee_id', "%{$search}%")))
            ->when($isActive !== null, fn (Builder $query) => $query->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOL)))
            ->when($branchId > 0, fn (Builder $query) => $query->whereHas('branchPermissions', fn (Builder $query) => $query->where('branch_id', $branchId)))
            ->orderBy('name')
            ->paginate(min(max($request->integer('per_page', 25), 1), 100));
    }
}
