<?php

namespace Modules\Shared\Patient\Data;

use Carbon\CarbonImmutable;
use Modules\Shared\Patient\Enums\BloodGroup;
use Modules\Shared\Patient\Enums\Gender;
use Modules\Shared\Patient\Enums\MaritalStatus;
use Modules\Shared\Patient\Enums\PatientTitle;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\BeforeOrEqual;
use Spatie\LaravelData\Attributes\Validation\Between;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\RequiredWithout;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * A patient as entered at the counter. Either the date of birth or the age in years is required; when the
 * date of birth is given the age is derived from it.
 */
#[MapName(SnakeCaseMapper::class)]
final class RegisterPatientData extends Data
{
    public function __construct(
        #[Max(255)]
        public string $name,
        #[RequiredWithout('date_of_birth'), Between(0, 150)]
        public ?int $ageYears,
        public ?PatientTitle $title = null,
        #[Max(1000)]
        public ?string $address = null,
        #[Between(0, 11)]
        public ?int $ageMonths = null,
        #[Between(0, 31)]
        public ?int $ageDays = null,
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d'), BeforeOrEqual('today')]
        public ?CarbonImmutable $dateOfBirth = null,
        public ?Gender $gender = null,
        public ?MaritalStatus $maritalStatus = null,
        public ?BloodGroup $bloodGroup = null,
        #[Max(20)]
        public ?string $phone = null,
        #[Max(30)]
        public ?string $nationalId = null,
    ) {}
}
