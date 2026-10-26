<?php

namespace Modules\Shared\Billing\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Billing\Database\Factories\BillingFactory;
use Modules\Shared\Billing\Enums\BillingStatus;
use Modules\Shared\Patient\Models\PatientMaster;

#[Table('sh_billing')]
#[Fillable(['invoice_no', 'patient_master_id', 'amount', 'status', 'billed_at', 'created_by'])]
#[UseFactory(BillingFactory::class)]
final class Billing extends Model
{
    /** @use HasFactory<BillingFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BillingStatus::class,
            'billed_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(PatientMaster::class, 'patient_master_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BillingItem::class, 'billing_id');
    }

    public function referral(): HasOne
    {
        return $this->hasOne(BillingReferral::class, 'billing_id');
    }
}
