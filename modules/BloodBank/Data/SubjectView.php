<?php

namespace Modules\BloodBank\Data;

use Modules\BloodBank\Enums\SampleSubject;
use Modules\BloodBank\Models\CaseDonor;
use Modules\Shared\Patient\Data\PatientSummary;

/**
 * Who a sample is drawn from: the case's patient (read from Shared) or a case donor (with `donor` loaded).
 * Rendered by SubjectResource.
 */
final readonly class SubjectView
{
    public function __construct(
        public SampleSubject $type,
        public ?CaseDonor $caseDonor = null,
        public ?PatientSummary $patient = null,
    ) {}
}
