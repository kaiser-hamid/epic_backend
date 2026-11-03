<?php

namespace Modules\BloodBank\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Between;
use Spatie\LaravelData\Attributes\Validation\LessThan;
use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * A donor's vitals about 15 minutes after donation. The ranges are the physical examination's and only catch
 * typos. The time is stamped by the server.
 */
#[MapName(SnakeCaseMapper::class)]
final class PostDonationVitalsData extends Data
{
    public function __construct(
        public int $caseDonorId,
        #[Between(20, 250)]
        public int $pulseBpm,
        #[Between(50, 300)]
        public int $bpSystolic,
        #[Between(20, 200), LessThan('bp_systolic')]
        public int $bpDiastolic,
        #[Between(90, 110), Rule('decimal:0,1')]
        public float $temperatureF,
    ) {}
}
