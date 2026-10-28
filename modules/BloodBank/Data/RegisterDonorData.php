<?php

namespace Modules\BloodBank\Data;

use Carbon\CarbonImmutable;
use Modules\Shared\Patient\Enums\BloodGroup;
use Modules\Shared\Patient\Enums\Gender;
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
 * A new donor. Either the date of birth or the age is required; when the date of birth is given the age is
 * derived from it. `age` has no default value so its rule runs when it is missing.
 */
#[MapName(SnakeCaseMapper::class)]
final class RegisterDonorData extends Data
{
    public function __construct(
        #[Max(255)]
        public string $name,
        #[RequiredWithout('date_of_birth'), Between(1, 120)]
        public ?int $age,
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d'), BeforeOrEqual('today')]
        public ?CarbonImmutable $dateOfBirth = null,
        public ?Gender $gender = null,
        public ?BloodGroup $bloodGroup = null,
        #[Max(20)]
        public ?string $phone = null,
        #[Max(1000)]
        public ?string $address = null,
        #[Max(30)]
        public ?string $nationalId = null,
        public ?bool $isRegularVolunteer = null,
    ) {}
}
