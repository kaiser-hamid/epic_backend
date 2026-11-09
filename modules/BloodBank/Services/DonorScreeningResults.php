<?php

namespace Modules\BloodBank\Services;

use Modules\BloodBank\Enums\SerologyTest;
use Modules\BloodBank\Models\CaseDonor;
use Modules\BloodBank\Models\TestDefinition;
use Modules\BloodBank\Models\TestResult;

/**
 * A donor's current screening results (blood group excluded) in the serology form's order, as printed on the
 * component label and the Compatibility Test Report. A test with no saved result (a donor whose serology was saved
 * before the test was added) is left out, or listed with a null result when the caller asks for every test.
 */
final class DonorScreeningResults
{
    public function __construct(private readonly DonorSerologyGate $serologyGate) {}

    /**
     * @param  bool  $includeUntested  list the tests with no saved result too, with a null result
     * @return list<array{code: string, name: string, method: string|null, result: string|null}>
     */
    public function forCaseDonor(CaseDonor $caseDonor, bool $includeUntested = false): array
    {
        $results = $this->serologyGate->sampleFor($caseDonor)?->results
            ->loadMissing('testDefinition')
            ->where('is_current', true)
            ->keyBy(fn (TestResult $result): string => $result->testDefinition->code);

        $definitions = $includeUntested
            ? TestDefinition::query()->whereIn('code', array_map(fn (SerologyTest $test): string => $test->value, SerologyTest::cases()))->get()->keyBy('code')
            : collect();
        $screening = [];

        foreach (SerologyTest::cases() as $test) {
            if (! $test->isScreening()) {
                continue;
            }

            $result = $results?->get($test->value);

            $definition = $result?->testDefinition ?? $definitions->get($test->value);

            if ($definition === null) {
                continue;
            }

            $screening[] = [
                'code' => $test->value,
                'name' => $definition->name,
                'method' => $definition->method,
                'result' => $result?->result_value,
            ];
        }

        return $screening;
    }
}
