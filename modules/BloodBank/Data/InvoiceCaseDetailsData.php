<?php

namespace Modules\BloodBank\Data;

use Modules\BloodBank\Enums\CareType;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The Blood Bank fields of a Blood Bank bill (`category_details`): "Care of" (daycare / takeaway). The case is
 * opened with it; cause of transfusion and notes are entered on the case afterwards.
 */
#[MapName(SnakeCaseMapper::class)]
final class InvoiceCaseDetailsData extends Data
{
    public function __construct(
        public CareType $careType,
    ) {}
}
