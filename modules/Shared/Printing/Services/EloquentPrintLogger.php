<?php

namespace Modules\Shared\Printing\Services;

use App\Models\User;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Shared\Printing\Contracts\PrintLogger;
use Modules\Shared\Printing\Exceptions\ReprintReasonRequired;
use Modules\Shared\Printing\Models\PrintLog;

final class EloquentPrintLogger implements PrintLogger
{
    public function record(BackedEnum $documentType, Model $printable, User $printedBy, ?string $reprintReason = null): PrintLog
    {
        $isReprint = $this->printsOf($documentType, $printable)->exists();

        if ($isReprint && blank($reprintReason)) {
            throw new ReprintReasonRequired;
        }

        return PrintLog::create([
            'document_type' => $documentType->value,
            'printable_type' => $printable->getMorphClass(),
            'printable_id' => $printable->getKey(),
            'printed_by' => $printedBy->getKey(),
            'printed_at' => now(),
            'is_reprint' => $isReprint,
            'reprint_reason' => $isReprint ? $reprintReason : null,
        ]);
    }

    public function printCount(BackedEnum $documentType, Model $printable): int
    {
        return $this->printsOf($documentType, $printable)->count();
    }

    /**
     * @return Builder<PrintLog>
     */
    private function printsOf(BackedEnum $documentType, Model $printable): Builder
    {
        return PrintLog::query()
            ->where('document_type', $documentType->value)
            ->where('printable_type', $printable->getMorphClass())
            ->where('printable_id', $printable->getKey());
    }
}
