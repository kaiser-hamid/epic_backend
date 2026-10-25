<?php

use App\Models\User;
use Modules\BloodBank\Enums\PrintDocumentType;
use Modules\BloodBank\Models\Sample;
use Modules\BloodBank\Models\TestResult;
use Modules\Shared\Printing\Contracts\PrintLogger;

it('keeps previous test result versions and exposes only the current one', function () {
    $previous = TestResult::factory()->create(['is_current' => false]);
    $current = TestResult::factory()->create([
        'sample_id' => $previous->sample_id,
        'test_definition_id' => $previous->test_definition_id,
        'version' => 2,
        'amendment_reason' => 'Transcription error',
    ]);

    expect(TestResult::current()->pluck('id')->all())->toBe([$current->id])
        ->and(TestResult::count())->toBe(2);
});

it('logs blood bank prints in the shared table under a morph alias', function () {
    $sample = Sample::factory()->create();

    $log = app(PrintLogger::class)->record(PrintDocumentType::SampleLabel, $sample, User::factory()->create());

    expect($log->getTable())->toBe('sh_print_logs')
        ->and($log->printable_type)->toBe('bb.sample')
        ->and($log->document_type)->toBe('bb.sample_label')
        ->and($log->printable->is($sample))->toBeTrue();
});
