<?php

namespace Modules\BloodBank\Data;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\BeforeOrEqual;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The rows ticked on the collection screen. `collected_at` defaults to now; it may be back-dated, never
 * future-dated.
 */
#[MapName(SnakeCaseMapper::class)]
final class CollectSamplesData extends Data
{
    /**
     * @param  list<SampleSubjectData>  $subjects
     */
    public function __construct(
        #[DataCollectionOf(SampleSubjectData::class), Min(1), Max(20)]
        public array $subjects,
        #[BeforeOrEqual('now')]
        public ?CarbonImmutable $collectedAt = null,
    ) {}

    /**
     * `distinct` has to be declared on the wildcard: laravel-data expands collection rules per index.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'subjects.*.case_donor_id' => ['distinct'],
        ];
    }
}
