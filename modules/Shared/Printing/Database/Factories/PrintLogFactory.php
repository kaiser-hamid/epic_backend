<?php

namespace Modules\Shared\Printing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Billing\Models\Billing;
use Modules\Shared\Printing\Models\PrintLog;

/**
 * @extends Factory<PrintLog>
 */
final class PrintLogFactory extends Factory
{
    protected $model = PrintLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_type' => 'sh.invoice',
            'printable_type' => (new Billing)->getMorphClass(),
            'printable_id' => Billing::factory(),
            'printed_by' => User::factory(),
            'printed_at' => now(),
            'is_reprint' => false,
        ];
    }

    public function reprint(string $reason = 'Original damaged'): static
    {
        return $this->state(fn (): array => [
            'is_reprint' => true,
            'reprint_reason' => $reason,
        ]);
    }
}
