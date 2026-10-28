<?php

namespace Modules\BloodBank\Data;

use Modules\BloodBank\Enums\CareType;
use Modules\Shared\Billing\Data\CreateInvoiceData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The Blood Bank registration form: the patient's bill (new or returning patient, tests, referral), the case
 * details, the requested components and the donors the patient brought.
 */
#[MapName(SnakeCaseMapper::class)]
final class RegisterCaseData extends Data
{
    /**
     * @param  list<CaseComponentData>  $components
     * @param  list<CaseDonorData>  $donors
     */
    public function __construct(
        public CreateInvoiceData $billing,
        public CareType $careType,
        #[Max(1000)]
        public ?string $transfusionReason = null,
        #[Max(1000)]
        public ?string $referenceNote = null,
        #[DataCollectionOf(CaseComponentData::class)]
        public array $components = [],
        #[DataCollectionOf(CaseDonorData::class)]
        public array $donors = [],
    ) {}

    /**
     * `distinct` has to be declared on the wildcard: laravel-data expands collection rules per index.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'billing.items.*.test_item_id' => ['distinct'],
            'components.*.component_type_id' => ['distinct'],
            'donors.*.donor_id' => ['distinct'],
        ];
    }
}
