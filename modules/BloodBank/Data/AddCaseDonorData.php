<?php

namespace Modules\BloodBank\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\AlphaNumeric;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Prohibits;
use Spatie\LaravelData\Attributes\Validation\RequiredWithout;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * A donor added to an existing case: the vendor's invoice number for the donor charge, and a returning donor
 * (`donor_id`, found by phone) or a new one (`donor`), never both. The donor properties have no default value so
 * the either/or rules run when both are missing.
 */
#[MapName(SnakeCaseMapper::class)]
final class AddCaseDonorData extends Data
{
    public function __construct(
        #[AlphaNumeric, Max(30)]
        public string $invoiceNo,
        #[RequiredWithout('donor'), Prohibits('donor'), Exists('bb_donors', 'id', withoutTrashed: true)]
        public ?int $donorId,
        #[RequiredWithout('donor_id')]
        public ?RegisterDonorData $donor,
    ) {}
}
