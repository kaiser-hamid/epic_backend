<?php

namespace Modules\BloodBank\Queries;

use Illuminate\Database\Eloquent\Collection;
use Modules\BloodBank\Models\Donor;
use Modules\Shared\Support\Data\PhoneLookupData;

/**
 * Donors of every branch with exactly this phone number, newest first, to pre-fill the form with a returning
 * donor. Capped at 10 rows.
 */
final class LookupDonors
{
    private const LIMIT = 10;

    /**
     * @return Collection<int, Donor>
     */
    public function get(PhoneLookupData $data): Collection
    {
        return Donor::query()
            ->where('phone', $data->phone)
            ->latest()
            ->limit(self::LIMIT)
            ->get();
    }
}
