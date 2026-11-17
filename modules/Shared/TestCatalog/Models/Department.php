<?php

namespace Modules\Shared\TestCatalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Shared\TestCatalog\Database\Factories\DepartmentFactory;
use Modules\Shared\TestCatalog\Enums\DepartmentType;

#[Table('sh_departments')]
#[Fillable(['code', 'name', 'type', 'is_active'])]
#[UseFactory(DepartmentFactory::class)]
final class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DepartmentType::class,
            'is_active' => 'boolean',
        ];
    }

    public function testItems(): HasMany
    {
        return $this->hasMany(TestItem::class, 'department_id');
    }

    /**
     * Its room in each branch (non-lab departments).
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(DepartmentRoom::class, 'department_id');
    }
}
