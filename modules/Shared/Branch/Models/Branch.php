<?php

namespace Modules\Shared\Branch\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Shared\Branch\Database\Factories\BranchFactory;

/**
 * A physical branch of the company. Every permission is granted per branch, and a session works in exactly one.
 */
#[Table('sh_branches')]
#[Fillable(['code', 'name', 'address', 'phone', 'is_active'])]
#[UseFactory(BranchFactory::class)]
final class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
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

    /**
     * @param  Builder<self>  $query
     */
    protected function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
