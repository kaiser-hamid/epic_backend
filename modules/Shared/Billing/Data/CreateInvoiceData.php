<?php

namespace Modules\Shared\Billing\Data;

use Modules\Shared\Patient\Data\RegisterPatientData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\AlphaNumeric;
use Spatie\LaravelData\Attributes\Validation\ArrayType;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Prohibits;
use Spatie\LaravelData\Attributes\Validation\RequiredWithout;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Support\Validation\Constraints\WhereConstraint;

/**
 * A counter bill: a returning patient (`patient_id`) or a new one registered on the bill (`patient`), the
 * billed tests, the referral, the patient type and at most one of its discount policies. Totals are computed
 * server-side.
 *
 * - `idempotency_key`: one UUID per form; resubmitting it returns the bill already saved instead of a second one.
 * - `invoice_no`: the vendor's number while both billing systems run; when blank the branch's next number is used.
 * - `category_details`: fields owned by the module of the bill's category (a Blood Bank bill's `care_type`);
 *   validated by that module, prohibited for a category without one.
 *
 * Conditionally required properties have no default value: laravel-data skips every rule of a defaulted
 * property that is missing from the payload.
 */
#[MapName(SnakeCaseMapper::class)]
final class CreateInvoiceData extends Data
{
    /**
     * @param  list<InvoiceItemData>  $items
     * @param  array<string, mixed>|null  $categoryDetails
     */
    public function __construct(
        #[Uuid]
        public string $idempotencyKey,
        #[DataCollectionOf(InvoiceItemData::class), Min(1)]
        public array $items,
        public InvoiceReferralData $referral,
        #[RequiredWithout('patient'), Prohibits('patient'), Exists('sh_patient_master', 'id', withoutTrashed: true)]
        public ?int $patientId,
        #[RequiredWithout('patient_id')]
        public ?RegisterPatientData $patient,
        #[Exists('sh_patient_types', 'id', withoutTrashed: true, where: new WhereConstraint('is_active', true))]
        public int $patientTypeId,
        public ?int $discountPolicyId = null,
        #[AlphaNumeric, Max(30)]
        public ?string $invoiceNo = null,
        #[ArrayType]
        public ?array $categoryDetails = null,
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
