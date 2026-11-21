<?php

namespace Modules\BloodBank\Data;

use Illuminate\Validation\Rule;
use Modules\BloodBank\Enums\SampleSubject;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Support\Validation\ValidationContext;

/**
 * One ticked row of the collection screen: the patient, a donor of the case (`case_donor_id`), or a stock bag
 * scanned on the screen and not yet on the case (`unit_no`; Collect adds it as a stock row and holds it). A donor
 * row has exactly one of the two.
 */
#[MapName(SnakeCaseMapper::class)]
final class SampleSubjectData extends Data
{
    public function __construct(
        public SampleSubject $subjectType,
        public ?int $caseDonorId = null,
        public ?string $unitNo = null,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        $isPatient = ($context->payload['subject_type'] ?? null) === SampleSubject::Patient->value;
        $hasUnitNo = filled($context->payload['unit_no'] ?? null);

        return [
            'case_donor_id' => ['nullable', 'integer', Rule::prohibitedIf($isPatient || $hasUnitNo), Rule::requiredIf(! $isPatient && ! $hasUnitNo)],
            'unit_no' => ['nullable', 'string', 'max:50', Rule::prohibitedIf($isPatient)],
        ];
    }

    public function isStockBag(): bool
    {
        return $this->subjectType === SampleSubject::Donor && $this->unitNo !== null;
    }
}
