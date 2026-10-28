<?php

namespace Modules\BloodBank\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Prohibits;
use Spatie\LaravelData\Attributes\Validation\RequiredWithout;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * A donor brought for the case: a returning donor (`donor_id`) or a new one (`donor`), never both.
 * Neither property has a default value so the either/or rules run when both are missing.
 */
#[MapName(SnakeCaseMapper::class)]
final class CaseDonorData extends Data
{
    public function __construct(
        #[RequiredWithout('donor'), Prohibits('donor'), Exists('bb_donors', 'id', withoutTrashed: true)]
        public ?int $donorId,
        #[RequiredWithout('donor_id')]
        public ?RegisterDonorData $donor,
    ) {}
}
