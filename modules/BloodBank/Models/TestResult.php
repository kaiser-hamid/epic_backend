<?php

namespace Modules\BloodBank\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\BloodBank\Database\Factories\TestResultFactory;
use Modules\BloodBank\Enums\TestResultStatus;
use Modules\Shared\Auth\Models\User;

#[Table('bb_test_results')]
#[Fillable(['sample_id', 'test_definition_id', 'version', 'is_current', 'result_value', 'observation', 'tested_by', 'tested_at', 'status', 'reviewed_by', 'reviewed_at', 'amendment_reason'])]
#[UseFactory(TestResultFactory::class)]
final class TestResult extends Model
{
    /** @use HasFactory<TestResultFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'status' => TestResultStatus::class,
            'tested_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function sample(): BelongsTo
    {
        return $this->belongsTo(Sample::class, 'sample_id');
    }

    public function testDefinition(): BelongsTo
    {
        return $this->belongsTo(TestDefinition::class, 'test_definition_id');
    }

    public function tester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    #[Scope]
    protected function current(Builder $query): void
    {
        $query->where('is_current', true);
    }
}
