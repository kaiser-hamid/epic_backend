<?php

namespace Modules\Shared\Billing\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Support\Validation\Constraints\WhereConstraint;

/**
 * The parts of a bill that decide its money, sent while the bill is being filled in to show server-computed totals.
 */
#[MapName(SnakeCaseMapper::class)]
final class QuoteInvoiceData extends Data
{
    /**
     * @param  list<InvoiceItemData>  $items
     */
    public function __construct(
        #[DataCollectionOf(InvoiceItemData::class), Min(1)]
        public array $items,
        #[Exists('sh_patient_types', 'id', withoutTrashed: true, where: new WhereConstraint('is_active', true))]
        public int $patientTypeId,
        public ?int $discountPolicyId = null,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'items.*.test_item_id' => ['distinct'],
        ];
    }
}
