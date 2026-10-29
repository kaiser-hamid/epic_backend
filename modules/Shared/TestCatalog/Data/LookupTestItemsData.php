<?php

namespace Modules\Shared\TestCatalog\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\ArrayType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * `?search=` of the test item lookup, plus the test items already picked on the form (`?exclude_ids[]=`),
 * which are never offered again.
 */
#[MapName(SnakeCaseMapper::class)]
final class LookupTestItemsData extends Data
{
    /**
     * @param  list<int>  $excludeIds
     */
    public function __construct(
        #[Max(100)]
        public string $search,
        #[ArrayType, Max(200)]
        public array $excludeIds = [],
    ) {}

    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return ['exclude_ids.*' => ['integer']];
    }
}
