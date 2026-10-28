<?php

namespace Modules\Shared\Billing\Data;

use Modules\Shared\Patient\Data\RegisterPatientData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Prohibits;
use Spatie\LaravelData\Attributes\Validation\RequiredWithout;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * A counter bill: a returning patient (`patient_id`) or a new one registered on the bill (`patient`), the
 * billed tests and the referral. Totals are computed server-side.
 *
 * Conditionally required properties have no default value: laravel-data skips every rule of a defaulted
 * property that is missing from the payload.
 */
#[MapName(SnakeCaseMapper::class)]
final class CreateInvoiceData extends Data
{
    /**
     * @param  list<InvoiceItemData>  $items
     */
    public function __construct(
        #[DataCollectionOf(InvoiceItemData::class), Min(1)]
        public array $items,
        public InvoiceReferralData $referral,
        #[RequiredWithout('patient'), Prohibits('patient'), Exists('sh_patient_master', 'id', withoutTrashed: true)]
        public ?int $patientId,
        #[RequiredWithout('patient_id')]
        public ?RegisterPatientData $patient,
    ) {}

    /**
     * `distinct` has to be declared on the wildcard: laravel-data expands collection rules per index.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'items.*.test_item_id' => ['distinct'],
        ];
    }
}
