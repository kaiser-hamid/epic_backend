<?php

namespace Modules\Shared\Patient\Data;

use Carbon\CarbonImmutable;
use Modules\Shared\Patient\Enums\BloodGroup;
use Modules\Shared\Patient\Enums\Gender;
use Modules\Shared\Patient\Enums\PatientTitle;
use Modules\Shared\Patient\Models\PatientMaster;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

/**
 * The patient details other modules show next to their own records (case lists, reports).
 */
#[MapName(SnakeCaseMapper::class)]
final class PatientSummary extends Data
{
    public function __construct(
        public int $id,
        public string $patientNo,
        public ?PatientTitle $title,
        public string $name,
        public ?int $ageYears,
        public ?int $ageMonths,
        public ?int $ageDays,
        #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
        public ?CarbonImmutable $dateOfBirth,
        public ?Gender $gender,
        public ?BloodGroup $bloodGroup,
        public ?string $phone,
        public ?string $address,
    ) {}

    public static function fromModel(PatientMaster $patient): self
    {
        return new self(
            id: $patient->id,
            patientNo: $patient->patient_no,
            title: $patient->title,
            name: $patient->name,
            ageYears: $patient->age_years,
            ageMonths: $patient->age_months,
            ageDays: $patient->age_days,
            dateOfBirth: $patient->date_of_birth?->toImmutable(),
            gender: $patient->gender,
            bloodGroup: $patient->blood_group,
            phone: $patient->phone,
            address: $patient->address,
        );
    }
}
