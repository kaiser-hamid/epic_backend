<?php

namespace Modules\BloodBank\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * One component label (the row's "Label" button) or several ("Label (All)"). `reprint_reason` is required when
 * any of them was printed before.
 */
#[MapName(SnakeCaseMapper::class)]
final class PrintUnitLabelsData extends Data
{
    /**
     * @param  list<int>  $unitIds
     */
    public function __construct(
        #[Min(1), Max(20)]
        public array $unitIds,
        #[Max(500)]
        public ?string $reprintReason = null,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'unit_ids.*' => ['integer', 'distinct'],
        ];
    }
}
