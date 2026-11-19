<?php

namespace Modules\BloodBank\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Between;
use Spatie\LaravelData\Attributes\Validation\LessThan;
use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Pulse, blood pressure and temperature taken at one moment. The ranges are the physical examination's and only
 * catch typos.
 */
#[MapName(SnakeCaseMapper::class)]
final class VitalsData extends Data
{
    public function __construct(
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
