<?php

namespace Modules\BloodBank\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * `?invoice_no=` scanned or typed on the sample collection screen: the case bill or a replacement donor's bill.
 * Staff still scan the vendor invoice, so the case no is not used yet; it takes over once the vendor system is
 * replaced.
 */
#[MapName(SnakeCaseMapper::class)]
final class SampleCollectionLookupData extends Data
{
    public function __construct(
        #[Max(30)]
        public string $invoiceNo,
        // #[Max(30)]
        // public string $caseNo,
    ) {}
}
