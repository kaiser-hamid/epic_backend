<?php

namespace Modules\BloodBank\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * One label (the row's "Label" button) or several ("Label (All)"). `reprint_reason` is required when any of
 * them was printed before.
 */
#[MapName(SnakeCaseMapper::class)]
final class PrintSampleLabelsData extends Data
{
    /**
     * @param  list<int>  $sampleIds
     */
    public function __construct(
        #[Min(1), Max(20)]
        public array $sampleIds,
        #[Max(500)]
        public ?string $reprintReason = null,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'sample_ids.*' => ['integer', 'distinct'],
        ];
    }
}
