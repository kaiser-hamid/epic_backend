<?php

namespace Modules\BloodBank\Data;

use Modules\BloodBank\Enums\SampleSubject;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\ProhibitedIf;
use Spatie\LaravelData\Attributes\Validation\RequiredIf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * One ticked row of the collection screen: the patient, or a donor of the case (`case_donor_id`).
 */
#[MapName(SnakeCaseMapper::class)]
final class SampleSubjectData extends Data
{
    public function __construct(
        public SampleSubject $subjectType,
        #[RequiredIf('subject_type', SampleSubject::Donor), ProhibitedIf('subject_type', SampleSubject::Patient)]
        public ?int $caseDonorId = null,
    ) {}
}
