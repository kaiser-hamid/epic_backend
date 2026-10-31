<?php

namespace Modules\BloodBank\Data;

use Modules\BloodBank\Models\Sample;

/**
 * One row of the collection screen: the subject, its latest sample (null when none was drawn yet) and whether
 * a sample can be collected now.
 */
final readonly class CollectionSubject
{
    public function __construct(
        public SubjectView $subject,
        public ?Sample $sample,
        public bool $isCollectable,
    ) {}
}
