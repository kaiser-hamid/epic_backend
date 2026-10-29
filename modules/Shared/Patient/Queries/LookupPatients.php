<?php

namespace Modules\Shared\Patient\Queries;

use Illuminate\Database\Eloquent\Collection;
use Modules\Shared\Patient\Models\PatientMaster;
use Modules\Shared\Support\Data\PhoneLookupData;

/**
 * Patients of every branch with exactly this phone number, newest first, to pre-fill the form with a returning
 * patient. Capped at 10 rows; a family often shares one number.
 */
final class LookupPatients
{
    private const LIMIT = 10;

    /**
     * @return Collection<int, PatientMaster>
     */
    public function get(PhoneLookupData $data): Collection
    {
        return PatientMaster::query()
            ->where('phone', $data->phone)
            ->latest()
            ->limit(self::LIMIT)
            ->get();
    }
}
