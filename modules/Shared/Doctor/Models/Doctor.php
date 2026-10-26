<?php

namespace Modules\Shared\Doctor\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Doctor\Database\Factories\DoctorFactory;

#[Table('sh_doctors')]
#[Fillable(['code', 'name', 'degree', 'address', 'phone', 'is_active', 'created_by'])]
#[UseFactory(DoctorFactory::class)]
final class Doctor extends Model
{
    /** @use HasFactory<DoctorFactory> */
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
