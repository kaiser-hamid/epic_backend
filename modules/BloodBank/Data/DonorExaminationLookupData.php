<?php

namespace Modules\BloodBank\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * `?invoice_no=` scanned or typed on the donor examination screen: the case bill or a replacement donor's bill.
 */
#[MapName(SnakeCaseMapper::class)]
final class DonorExaminationLookupData extends Data
{
    public function __construct(
        #[Max(30)]
        public string $invoiceNo,
    ) {}
}
