<?php

namespace Modules\Shared\Patient\Services;

use Illuminate\Database\Eloquent\Builder;
use Modules\Shared\Patient\Contracts\PatientDirectory;
use Modules\Shared\Patient\Data\PatientSummary;
use Modules\Shared\Patient\Models\PatientMaster;

final class EloquentPatientDirectory implements PatientDirectory
{
    public function summaries(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return PatientMaster::query()
            ->whereKey($ids)
            ->get()
            ->mapWithKeys(fn (PatientMaster $patient): array => [$patient->id => PatientSummary::fromModel($patient)])
            ->all();
    }

    public function idsMatching(string $search, int $limit = 1000): array
    {
        return PatientMaster::query()
            ->where(fn (Builder $query) => $query
                ->whereLike('name', "%{$search}%")
                ->orWhereLike('phone', "%{$search}%")
                ->orWhereLike('patient_no', "%{$search}%"))
            ->latest('id')
            ->limit($limit)
            ->pluck('id')
            ->all();
    }
}
