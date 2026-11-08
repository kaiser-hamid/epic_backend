<?php

namespace Modules\BloodBank\Data;

use Modules\Shared\Patient\Enums\BloodGroup;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The "Issue Blood" form. `patient_blood_group` is required until the patient sample has a blood group; changing
 * a saved one needs `amendment_reason`. The Medical Technologist and the Physician who sign the report are
 * picked from staff. Each unit carries its crossmatch; `post_donation` optionally holds the vitals of donors not
 * recorded yet; blood can be issued without them.
 */
#[MapName(SnakeCaseMapper::class)]
final class IssueBloodData extends Data
{
    /**
     * @param  list<CrossmatchUnitData>  $units
     * @param  list<PostDonationVitalsData>  $postDonation
     */
    public function __construct(
        public int $technologistId,
        public int $physicianId,
        #[DataCollectionOf(CrossmatchUnitData::class), Min(1), Max(20)]
        public array $units,
        public ?BloodGroup $patientBloodGroup = null,
        #[Max(1000)]
        public ?string $amendmentReason = null,
        #[DataCollectionOf(PostDonationVitalsData::class), Max(20)]
        public array $postDonation = [],
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'technologist_id' => ['exists:users,id'],
            'physician_id' => ['exists:users,id'],
            'units.*.unit_id' => ['distinct'],
            'post_donation.*.case_donor_id' => ['distinct'],
        ];
    }
}
