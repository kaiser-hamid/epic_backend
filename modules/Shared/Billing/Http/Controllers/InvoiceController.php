<?php

namespace Modules\Shared\Billing\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Shared\Billing\Actions\CreateInvoice;
use Modules\Shared\Billing\Data\CreateInvoiceData;
use Modules\Shared\Billing\Http\Resources\InvoiceResource;
use Modules\Shared\Billing\Models\Billing;
use Modules\Shared\Billing\Queries\ListInvoices;
use Modules\Shared\Billing\Queries\ShowInvoice;

final class InvoiceController
{
    public function __construct(
        private readonly ListInvoices $listInvoices,
        private readonly ShowInvoice $showInvoice,
        private readonly CreateInvoice $createInvoice,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return InvoiceResource::collection($this->listInvoices->get($request));
    }

    public function store(Request $request, CreateInvoiceData $data): InvoiceResource
    {
        return InvoiceResource::make($this->createInvoice->handle($request->user(), $data));
    }

    public function show(Billing $billing): InvoiceResource
    {
        return InvoiceResource::make($this->showInvoice->get($billing));
    }
}
