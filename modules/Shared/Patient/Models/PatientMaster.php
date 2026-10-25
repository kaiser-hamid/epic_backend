<?php

namespace Modules\Shared\Patient\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Shared\Patient\Database\Factories\PatientMasterFactory;
use Modules\Shared\Patient\Enums\BloodGroup;
use Modules\Shared\Patient\Enums\Gender;
use Modules\Shared\Patient\Enums\MaritalStatus;
use Modules\Shared\Patient\Enums\PatientTitle;

#[Table('sh_patient_master')]
#[Fillable(['patient_no', 'title', 'name', 'address', 'age_years', 'age_months', 'age_days', 'date_of_birth', 'gender', 'marital_status', 'blood_group', 'phone', 'national_id', 'created_by'])]
#[UseFactory(PatientMasterFactory::class)]
final class PatientMaster extends Model
{
    /** @use HasFactory<PatientMasterFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'title' => PatientTitle::class,
            'date_of_birth' => 'date',
            'gender' => Gender::class,
            'marital_status' => MaritalStatus::class,
            'blood_group' => BloodGroup::class,
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
