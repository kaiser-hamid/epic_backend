<?php

namespace Modules\Shared\Patient\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Shared\Audit\Contracts\AuditLogger;
use Modules\Shared\Audit\Enums\AuditAction;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Patient\Data\RegisterPatientData;
use Modules\Shared\Patient\Models\PatientMaster;
use Modules\Shared\Patient\Services\PatientNumber;

/**
 * Registers a patient in the shared patient master, visible from every branch.
 */
final class RegisterPatient
{
    public function __construct(
        private readonly PatientNumber $patientNumber,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(User $actor, RegisterPatientData $data): PatientMaster
    {
        return DB::transaction(function () use ($actor, $data): PatientMaster {
            $patient = PatientMaster::create([
                'patient_no' => $this->patientNumber->next(),
                'title' => $data->title,
                'name' => $data->name,
                'address' => $data->address,
                ...$this->age($data),
                'gender' => $data->gender,
                'marital_status' => $data->maritalStatus,
                'blood_group' => $data->bloodGroup,
                'phone' => $data->phone,
                'national_id' => $data->nationalId,
                'created_by' => $actor->id,
            ]);

            $this->auditLogger->record(AuditAction::PatientRegistered, $patient, newValues: $patient->only([
                'patient_no', 'name', 'age_years', 'date_of_birth', 'gender', 'phone', 'national_id',
            ]));

            return $patient;
        });
    }

    /**
     * Always stores both: a given date of birth wins and the age is derived from it; otherwise the date of
     * birth is back-calculated from the typed age (missing months/days count as 0).
     *
     * @return array{age_years: ?int, age_months: ?int, age_days: ?int, date_of_birth: CarbonImmutable}
     */
    private function age(RegisterPatientData $data): array
    {
        $today = CarbonImmutable::today();

        if ($data->dateOfBirth === null) {
            return [
                'age_years' => $data->ageYears,
                'age_months' => $data->ageMonths,
                'age_days' => $data->ageDays,
                'date_of_birth' => $today
                    ->subYearsNoOverflow($data->ageYears ?? 0)
                    ->subMonthsNoOverflow($data->ageMonths ?? 0)
                    ->subDays($data->ageDays ?? 0),
            ];
        }

        $dateOfBirth = $data->dateOfBirth->startOfDay();
        $age = $dateOfBirth->diff($today);

        return ['age_years' => $age->y, 'age_months' => $age->m, 'age_days' => $age->d, 'date_of_birth' => $dateOfBirth];
    }
}
