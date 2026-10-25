<?php

use App\Models\User;
use Modules\Shared\Billing\Models\Billing;
use Modules\Shared\Printing\Contracts\PrintLogger;
use Modules\Shared\Printing\Exceptions\ReprintReasonRequired;
use Modules\Shared\Printing\Models\PrintLog;

enum PrintLoggerTestDocument: string
{
    case Invoice = 'sh.invoice';
    case MoneyReceipt = 'sh.money_receipt';
}

beforeEach(function () {
    $this->logger = app(PrintLogger::class);
    $this->user = User::factory()->create();
    $this->billing = Billing::factory()->create();
});

it('records the first print as an original', function () {
    $log = $this->logger->record(PrintLoggerTestDocument::Invoice, $this->billing, $this->user);

    expect($log->is_reprint)->toBeFalse()
        ->and($log->reprint_reason)->toBeNull()
        ->and($log->document_type)->toBe('sh.invoice')
        ->and($log->printable->is($this->billing))->toBeTrue()
        ->and($log->printer->is($this->user))->toBeTrue();
});

it('records a later print of the same document as a reprint with its reason', function () {
    $this->logger->record(PrintLoggerTestDocument::Invoice, $this->billing, $this->user);

    $log = $this->logger->record(PrintLoggerTestDocument::Invoice, $this->billing, $this->user, 'Patient lost copy');

    expect($log->is_reprint)->toBeTrue()
        ->and($log->reprint_reason)->toBe('Patient lost copy')
        ->and($this->logger->printCount(PrintLoggerTestDocument::Invoice, $this->billing))->toBe(2);
});

it('refuses a reprint without a reason', function () {
    $this->logger->record(PrintLoggerTestDocument::Invoice, $this->billing, $this->user);

    $this->logger->record(PrintLoggerTestDocument::Invoice, $this->billing, $this->user, ' ');
})->throws(ReprintReasonRequired::class);

it('treats a different document for the same record as a first print', function () {
    $this->logger->record(PrintLoggerTestDocument::Invoice, $this->billing, $this->user);

    $log = $this->logger->record(PrintLoggerTestDocument::MoneyReceipt, $this->billing, $this->user);

    expect($log->is_reprint)->toBeFalse()
        ->and(PrintLog::count())->toBe(2);
});
