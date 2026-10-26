<?php

namespace Modules\Shared\Printing\Contracts;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Modules\Shared\Auth\Models\User;
use Modules\Shared\Printing\Exceptions\ReprintReasonRequired;
use Modules\Shared\Printing\Models\PrintLog;

/**
 * Records document prints for any module. Modules own their document types (a backed enum with
 * module-prefixed values) and who may reprint; this only records and enforces "reprint needs a reason".
 */
interface PrintLogger
{
    /**
     * Log one print. It is a reprint when the same document was already printed for the record.
     *
     * @throws ReprintReasonRequired
     */
    public function record(BackedEnum $documentType, Model $printable, User $printedBy, ?string $reprintReason = null): PrintLog;

    public function printCount(BackedEnum $documentType, Model $printable): int;
}
