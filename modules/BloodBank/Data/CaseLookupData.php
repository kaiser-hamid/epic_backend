<?php

namespace Modules\BloodBank\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * `?invoice_no=` typed or scanned to open a case: the case bill or an added donor's bill.
 */
#[MapName(SnakeCaseMapper::class)]
final class CaseLookupData extends Data
{
    public function __construct(
        #[Max(30)]
        public string $invoiceNo,
    ) {}
}
