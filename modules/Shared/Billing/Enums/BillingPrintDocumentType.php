<?php

namespace Modules\Shared\Billing\Enums;

/**
 * Billing's printable documents. Values are prefixed `sh.` because they share `sh_print_logs` with other modules.
 */
enum BillingPrintDocumentType: string
{
    case Invoice = 'sh.invoice';
}
