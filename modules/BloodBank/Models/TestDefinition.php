<?php

namespace Modules\BloodBank\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\BloodBank\Database\Factories\TestDefinitionFactory;
use Modules\BloodBank\Enums\TestAppliesTo;
use Modules\BloodBank\Enums\TestResultType;

#[Table('bb_test_definitions')]
#[Fillable(['code', 'name', 'result_type', 'unit', 'reference_range', 'method', 'options', 'applies_to', 'sort_order', 'is_active'])]
#[UseFactory(TestDefinitionFactory::class)]
final class TestDefinition extends Model
{
    /** @use HasFactory<TestDefinitionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'result_type' => TestResultType::class,
            'applies_to' => TestAppliesTo::class,
            'options' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function results(): HasMany
    {
        return $this->hasMany(TestResult::class, 'test_definition_id');
    }
}
