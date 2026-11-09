<?php

namespace Modules\Shared\Billing\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Shared\Billing\Actions\CreateInvoice;
use Modules\Shared\Billing\Actions\PrintInvoice;
use Modules\Shared\Billing\Data\CreateInvoiceData;
use Modules\Shared\Billing\Data\PrintInvoiceData;
use Modules\Shared\Billing\Data\QuoteInvoiceData;
use Modules\Shared\Billing\Http\Resources\InvoiceQuoteResource;
use Modules\Shared\Billing\Http\Resources\InvoiceResource;
use Modules\Shared\Billing\Models\Billing;
use Modules\Shared\Billing\Queries\ListInvoices;
use Modules\Shared\Billing\Queries\QuoteInvoice;
use Modules\Shared\Billing\Queries\ShowInvoice;
use Modules\Shared\Printing\Data\PdfDocument;

final class InvoiceController
{
    public function __construct(
        private readonly ListInvoices $listInvoices,
        private readonly ShowInvoice $showInvoice,
        private readonly CreateInvoice $createInvoice,
        private readonly QuoteInvoice $quoteInvoice,
        private readonly PrintInvoice $printInvoice,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return InvoiceResource::collection($this->listInvoices->get($request));
    }

    /**
     * 201 for a new bill; 200 with the bill already saved when the form's idempotency key is resubmitted.
     */
    public function store(Request $request, CreateInvoiceData $data): InvoiceResource
    {
        return InvoiceResource::make($this->createInvoice->handle($request->user(), $data));
    }

    public function quote(QuoteInvoiceData $data): InvoiceQuoteResource
    {
        return InvoiceQuoteResource::make($this->quoteInvoice->get($data));
    }

    public function show(Billing $billing): InvoiceResource
    {
        return InvoiceResource::make($this->showInvoice->get($billing));
    }

    public function print(Request $request, Billing $billing, PrintInvoiceData $data): PdfDocument
    {
        return $this->printInvoice->handle($request->user(), $billing, $data);
    }
}
