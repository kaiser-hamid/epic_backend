<?php

namespace Modules\Shared\Audit\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;
use Modules\Shared\Audit\Enums\AuditAction;
use Modules\Shared\Auth\Models\User;

/**
 * Append-only audit trail. `auditable_type` holds a morph alias. Rows are never updated or deleted.
 */
#[Table('sh_audit_logs')]
#[Fillable(['actor_id', 'branch_id', 'action', 'auditable_type', 'auditable_id', 'old_values', 'new_values', 'ip_address', 'user_agent'])]
final class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Audit logs are append-only.'));
        self::deleting(fn () => throw new LogicException('Audit logs are append-only.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
