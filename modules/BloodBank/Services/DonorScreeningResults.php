<?php

namespace Modules\BloodBank\Services;

use Modules\BloodBank\Enums\SerologyTest;
use Modules\BloodBank\Models\CaseDonor;
use Modules\BloodBank\Models\TestResult;

/**
 * A donor's current screening results (blood group excluded) in the serology form's order, as printed on the
 * component label and the Compatibility Test Report. A test not saved yet has a null result.
 */
final class DonorScreeningResults
{
    public function __construct(private readonly DonorSerologyGate $serologyGate) {}

    /**
     * @return list<array{code: string, name: string, method: string|null, result: string|null}>
     */
    public function forCaseDonor(CaseDonor $caseDonor): array
    {
        $results = $this->serologyGate->sampleFor($caseDonor)?->results
            ->loadMissing('testDefinition')
            ->where('is_current', true)
            ->keyBy(fn (TestResult $result): string => $result->testDefinition->code);

        $screening = [];

        foreach (SerologyTest::cases() as $test) {
            if (! $test->isScreening()) {
                continue;
            }

            $result = $results?->get($test->value);

            $screening[] = [
                'code' => $test->value,
                'name' => $result?->testDefinition->name ?? $test->value,
                'method' => $result?->testDefinition->method,
                'result' => $result?->result_value,
            ];
        }

        return $screening;
    }
}
