<?php

namespace Modules\Shared\Billing\Queries;

use Illuminate\Database\Eloquent\Collection;
use Modules\Shared\Billing\Models\PatientType;

/**
 * The patient types a bill can be raised under, each with the active discount policies staff may pick from.
 */
final class ListPatientTypes
{
    /**
     * @return Collection<int, PatientType>
     */
    public function get(): Collection
    {
        return PatientType::query()
            ->where('is_active', true)
            ->with(['discountPolicies' => fn ($query) => $query->where('sh_discount_policies.is_active', true)->orderBy('name')])
            ->orderBy('name')
            ->get();
    }
}
