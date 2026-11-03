<?php

namespace Modules\BloodBank\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The blood issue screen's scan: `?invoice_no=` (the case bill or a replacement donor's bill) or `?donor_no=`
 * (the donor card). Exactly one is sent.
 */
#[MapName(SnakeCaseMapper::class)]
final class BloodIssueLookupData extends Data
{
    public function __construct(
        public ?string $invoiceNo = null,
        public ?string $donorNo = null,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'invoice_no' => ['nullable', 'string', 'max:30', 'required_without:donor_no', 'prohibits:donor_no'],
            'donor_no' => ['nullable', 'string', 'max:30', 'required_without:invoice_no'],
        ];
    }
}
