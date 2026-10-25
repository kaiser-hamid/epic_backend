<?php

namespace Modules\Shared\Billing\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Shared\Billing\Database\Factories\BillingReferralFactory;
use Modules\Shared\Doctor\Models\Doctor;

#[Table('sh_billing_referrals')]
#[Fillable(['billing_id', 'doctor_id', 'second_doctor_id', 'is_self_referral', 'remark', 'referred_by_staff_id'])]
#[UseFactory(BillingReferralFactory::class)]
final class BillingReferral extends Model
{
    /** @use HasFactory<BillingReferralFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_self_referral' => 'boolean',
        ];
    }

    public function billing(): BelongsTo
    {
        return $this->belongsTo(Billing::class, 'billing_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'doctor_id');
    }

    public function secondDoctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'second_doctor_id');
    }

    public function referredByStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by_staff_id');
    }
}
