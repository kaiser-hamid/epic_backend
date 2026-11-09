<?php

use Modules\BloodBank\Enums\SampleSubject;
use Modules\BloodBank\Models\CaseDonor;
use Modules\BloodBank\Models\Sample;
use Modules\BloodBank\Models\TestDefinition;
use Modules\BloodBank\Models\TestResult;
use Modules\BloodBank\Services\DonorScreeningResults;

beforeEach(function () {
    $this->caseDonor = CaseDonor::factory()->create();
    $sample = Sample::factory()->create(['case_id' => $this->caseDonor->case_id, 'case_donor_id' => $this->caseDonor->id, 'subject_type' => SampleSubject::Donor]);
    TestResult::factory()->create([
        'sample_id' => $sample->id,
        'test_definition_id' => TestDefinition::where('code', 'SYPHILIS')->value('id'),
        'result_value' => 'non_reactive',
    ]);
});

it('lists the malarial parasite test with no result for a donor screened before it was added, when every test is asked for', function () {
    $screening = app(DonorScreeningResults::class);
    $all = $screening->forCaseDonor($this->caseDonor, includeUntested: true);

    expect(array_column($screening->forCaseDonor($this->caseDonor), 'code'))->toBe(['SYPHILIS'])
        ->and(array_column($all, 'code'))->toBe(['HBSAG', 'ANTI_HCV', 'ANTI_HIV', 'SYPHILIS', 'MALARIAL_PARASITE'])
        ->and(end($all))->toMatchArray(['name' => 'Malarial Parasite', 'method' => 'ICT method', 'result' => null])
        ->and($all[3]['result'])->toBe('non_reactive');
});
