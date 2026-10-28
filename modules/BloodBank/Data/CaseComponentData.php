<?php

namespace Modules\BloodBank\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Between;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Support\Validation\Constraints\WhereConstraint;

/**
 * A requested component, e.g. PCV × 2.
 */
#[MapName(SnakeCaseMapper::class)]
final class CaseComponentData extends Data
{
    public function __construct(
        #[Exists('bb_component_types', 'id', where: new WhereConstraint('is_active', true))]
        public int $componentTypeId,
        #[Between(1, 20)]
        public int $quantity = 1,
    ) {}
}
