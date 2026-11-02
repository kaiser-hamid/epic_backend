<?php

namespace Modules\BloodBank\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The serology screen's scan: `?invoice_no=` (the case bill or a replacement donor's bill) or `?sample_no=` (a
 * sample tube barcode). Exactly one is sent.
 */
#[MapName(SnakeCaseMapper::class)]
final class DonorSerologyLookupData extends Data
{
    public function __construct(
        public ?string $invoiceNo = null,
        public ?string $sampleNo = null,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'invoice_no' => ['nullable', 'string', 'max:30', 'required_without:sample_no', 'prohibits:sample_no'],
            'sample_no' => ['nullable', 'string', 'max:30', 'required_without:invoice_no'],
        ];
    }
}
