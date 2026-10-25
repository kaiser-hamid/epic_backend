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

#[Table('sh_departments')]
#[Fillable(['code', 'name', 'is_active'])]
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
            'is_active' => 'boolean',
        ];
    }

    public function testItems(): HasMany
    {
        return $this->hasMany(TestItem::class, 'department_id');
    }
}
