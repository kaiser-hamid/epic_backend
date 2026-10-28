<?php

namespace Modules\Shared\Billing\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Between;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Support\Validation\Constraints\WhereConstraint;

/**
 * One billed test. The rate is never taken from the client; it is snapshotted from the catalog.
 */
#[MapName(SnakeCaseMapper::class)]
final class InvoiceItemData extends Data
{
    public function __construct(
        #[Exists('sh_test_items', 'id', withoutTrashed: true, where: new WhereConstraint('is_active', true))]
        public int $testItemId,
        #[Between(1, 999)]
        public int $quantity = 1,
    ) {}
}
