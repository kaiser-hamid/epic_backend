<?php

namespace Modules\Shared\Numbering\Contracts;

/**
 * Hands out the next value of a named running sequence for human-facing numbers (invoice, patient, unit no).
 * Callers own the format; this only guarantees each value is issued once. Call it inside the caller's
 * transaction so a rolled-back record also gives its number back.
 */
interface NumberGenerator
{
    /**
     * @param  string  $scope  Keeps separate counters for the same sequence, e.g. one per branch.
     */
    public function next(string $sequence, string $scope = 'global'): int;
}
