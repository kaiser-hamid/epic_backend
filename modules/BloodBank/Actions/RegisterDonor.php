<?php

namespace Modules\BloodBank\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\BloodBank\Data\RegisterDonorData;
use Modules\BloodBank\Enums\BloodBankAuditAction;
use Modules\BloodBank\Models\Donor;
use Modules\BloodBank\Services\DonorNumber;
use Modules\Shared\Audit\Contracts\AuditLogger;
use Modules\Shared\Auth\Models\User;

/**
 * Registers a donor as a person, reusable across cases and branches.
 */
final class RegisterDonor
{
    public function __construct(
        private readonly DonorNumber $donorNumber,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(User $actor, RegisterDonorData $data): Donor
    {
        return DB::transaction(function () use ($actor, $data): Donor {
            $donor = Donor::create([
                'donor_no' => $this->donorNumber->next(),
                'name' => $data->name,
                'father_name' => $data->fatherName,
                'date_of_birth' => $data->dateOfBirth,
                'age' => $data->dateOfBirth === null ? $data->age : (int) $data->dateOfBirth->diffInYears(CarbonImmutable::today()),
                'gender' => $data->gender,
                'marital_status' => $data->maritalStatus,
                'religion' => $data->religion,
                'blood_group' => $data->bloodGroup,
                'phone' => $data->phone,
                'address' => $data->address,
                'national_id' => $data->nationalId,
                'occupation' => $data->occupation,
                'is_regular_volunteer' => $data->isRegularVolunteer,
                'created_by' => $actor->id,
            ]);

            $this->auditLogger->record(BloodBankAuditAction::DonorRegistered, $donor, newValues: $donor->only([
                'donor_no', 'name', 'father_name', 'age', 'date_of_birth', 'gender', 'marital_status', 'religion', 'blood_group', 'phone', 'national_id', 'occupation',
            ]));

            return $donor;
        });
    }
}
