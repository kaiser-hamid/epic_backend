<?php

namespace Modules\BloodBank\Queries;

use Illuminate\Database\Eloquent\Collection;
use Modules\BloodBank\Models\ComponentType;

/**
 * Every active blood component type, by name. Small fixed list for form dropdowns, so it is not paginated.
 */
final class ListComponentTypes
{
    /**
     * @return Collection<int, ComponentType>
     */
    public function get(): Collection
    {
        return ComponentType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
    }
}
