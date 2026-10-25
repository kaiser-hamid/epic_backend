<?php

namespace Modules\Shared\Printing\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Shared\Printing\Database\Factories\PrintLogFactory;

/**
 * Append-only record of every print and reprint across modules. `document_type` holds a
 * module-prefixed enum value (e.g. `bb.sample_label`); `printable_type` holds a morph alias.
 */
#[Table('sh_print_logs')]
#[Fillable(['document_type', 'printable_type', 'printable_id', 'printed_by', 'printed_at', 'is_reprint', 'reprint_reason'])]
#[UseFactory(PrintLogFactory::class)]
final class PrintLog extends Model
{
    /** @use HasFactory<PrintLogFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_reprint' => 'boolean',
            'printed_at' => 'datetime',
        ];
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'printed_by');
    }

    public function printable(): MorphTo
    {
        return $this->morphTo();
    }
}
