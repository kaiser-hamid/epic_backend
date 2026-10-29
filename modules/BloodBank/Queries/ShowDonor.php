<?php

namespace Modules\BloodBank\Queries;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\BloodBank\Data\DonorView;
use Modules\BloodBank\Models\Donor;
use Modules\Shared\Patient\Contracts\PatientDirectory;

/**
 * One donor with every case they were registered for, across all branches, newest first: the case, its patient
 * (who the blood was for), the eligibility outcome and the bags collected.
 */
final class ShowDonor
{
    public function __construct(private readonly PatientDirectory $patients) {}

    public function get(Donor $donor): DonorView
    {
        $donor->load([
            'caseDonors' => fn (HasMany $query) => $query->whereHas('patientCase')->latest()->orderByDesc('id'),
            'caseDonors.patientCase',
            'caseDonors.collections' => fn (HasMany $query) => $query->orderBy('collected_at'),
        ]);

        return new DonorView(
            $donor,
            $this->patients->summaries($donor->caseDonors->pluck('patientCase.patient_master_id')->unique()->values()->all()),
        );
    }
}
