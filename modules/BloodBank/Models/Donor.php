<?php

namespace Modules\BloodBank\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\BloodBank\Database\Factories\DonorFactory;
use Modules\Shared\Patient\Enums\BloodGroup;
use Modules\Shared\Patient\Enums\Gender;

#[Table('bb_donors')]
#[Fillable(['donor_no', 'name', 'date_of_birth', 'age', 'gender', 'blood_group', 'phone', 'address', 'national_id', 'is_regular_volunteer', 'created_by'])]
#[UseFactory(DonorFactory::class)]
final class Donor extends Model
{
    /** @use HasFactory<DonorFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'gender' => Gender::class,
            'blood_group' => BloodGroup::class,
            'is_regular_volunteer' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function caseDonors(): HasMany
    {
        return $this->hasMany(CaseDonor::class, 'donor_id');
    }
}
