<?php

namespace Modules\BloodBank\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\BloodBank\Database\Factories\PhlebotomyFactory;
use Modules\Shared\Auth\Models\User;

/**
 * A therapeutic phlebotomy of a case: the volume drawn and destroyed, optional pre/post vitals, who did it and
 * when. One per case; recording it closes the case.
 */
#[Table('bb_phlebotomies')]
#[Fillable([
    'case_id', 'volume_ml', 'pre_pulse_bpm', 'pre_bp_systolic', 'pre_bp_diastolic', 'pre_temperature_f',
    'post_pulse_bpm', 'post_bp_systolic', 'post_bp_diastolic', 'post_temperature_f', 'notes', 'performed_at', 'performed_by',
])]
#[UseFactory(PhlebotomyFactory::class)]
final class Phlebotomy extends Model
{
    /** @use HasFactory<PhlebotomyFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pre_temperature_f' => 'decimal:1',
            'post_temperature_f' => 'decimal:1',
            'performed_at' => 'datetime',
        ];
    }

    public function patientCase(): BelongsTo
    {
        return $this->belongsTo(PatientCase::class, 'case_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
