<?php

namespace Modules\BloodBank\Data;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\BeforeOrEqual;
use Spatie\LaravelData\Attributes\Validation\Between;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The "Therapeutic Phlebotomy – Mark done" form: the volume drawn and destroyed (ml), optional pre- and
 * post-procedure vitals, notes, and `performed_at` (defaults to now; may be back-dated, never future-dated).
 */
#[MapName(SnakeCaseMapper::class)]
final class RecordTherapeuticPhlebotomyData extends Data
{
    public function __construct(
        #[Between(50, 1000)]
        public int $volumeMl,
        public ?VitalsData $pre = null,
        public ?VitalsData $post = null,
        #[Max(500)]
        public ?string $notes = null,
        #[BeforeOrEqual('now')]
        public ?CarbonImmutable $performedAt = null,
    ) {}
}
