<?php

namespace Modules\Shared\Patient\Contracts;

use Modules\Shared\Patient\Data\PatientSummary;

/**
 * Read access to the patient master for other modules, which store only `patient_master_id`.
 */
interface PatientDirectory
{
    /**
     * @param  list<int>  $ids
     * @return array<int, PatientSummary> keyed by patient id; unknown ids are left out
     */
    public function summaries(array $ids): array;

    /**
     * Ids of patients whose name, phone or patient no contains the search text, newest first.
     * Capped at `$limit` so callers can use the result in a `whereIn`.
     *
     * @return list<int>
     */
    public function idsMatching(string $search, int $limit = 1000): array;
}
