<?php

namespace Modules\Shared\TestCatalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Shared\TestCatalog\Database\Factories\TestItemFactory;
use Modules\Shared\TestCatalog\Enums\TestItemCategory;

#[Table('sh_test_items')]
#[Fillable(['department_id', 'code', 'name', 'department_name', 'category', 'rate', 'discount_rate_bp', 'is_active'])]
#[UseFactory(TestItemFactory::class)]
final class TestItem extends Model
{
    /** @use HasFactory<TestItemFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => TestItemCategory::class,
            'is_active' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }
}
