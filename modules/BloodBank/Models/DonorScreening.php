<?php

namespace Modules\BloodBank\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\BloodBank\Database\Factories\DonorScreeningFactory;

/**
 * Physical examination for one donation: measured before donation, post-donation vitals added ~15 min after.
 */
#[Table('bb_donor_screenings')]
#[Fillable([
    'case_donor_id', 'weight_kg', 'height_cm', 'hemoglobin_g_dl',
    'pre_pulse_bpm', 'pre_bp_systolic', 'pre_bp_diastolic', 'pre_temperature_f', 'examined_by', 'examined_at',
    'post_pulse_bpm', 'post_bp_systolic', 'post_bp_diastolic', 'post_temperature_f', 'post_recorded_by', 'post_recorded_at',
])]
#[UseFactory(DonorScreeningFactory::class)]
final class DonorScreening extends Model
{
    /** @use HasFactory<DonorScreeningFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight_kg' => 'decimal:2',
            'height_cm' => 'decimal:1',
            'hemoglobin_g_dl' => 'decimal:1',
            'pre_temperature_f' => 'decimal:1',
            'post_temperature_f' => 'decimal:1',
            'examined_at' => 'datetime',
            'post_recorded_at' => 'datetime',
        ];
    }

    public function caseDonor(): BelongsTo
    {
        return $this->belongsTo(CaseDonor::class, 'case_donor_id');
    }

    public function examiner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'examined_by');
    }

    public function postRecorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'post_recorded_by');
    }
}
