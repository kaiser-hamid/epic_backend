{{--
    Bill (invoice), A4 portrait. Provisional layout until the paper size and design are confirmed. Rendered by mPDF
    (PrintInvoice); keep to tables and simple CSS. Money is integer paisa, shown in taka.
--}}
@php
    $taka = fn (int $paisa): string => number_format($paisa / 100, 2);
    $patient = $billing->patient;
    $referral = $billing->referral;
    $discount = $billing->discounts->first();
    $age = $patient->ageLabel();
@endphp
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: freesans; font-size: 10pt; color: #000; }
        table { border-collapse: collapse; width: 100%; }
        .title { text-align: center; font-size: 15pt; font-weight: bold; margin-bottom: 1mm; }
        .reprint { text-align: center; font-size: 10pt; font-weight: bold; letter-spacing: 1pt; margin-bottom: 2mm; }
        .meta td { padding: 0.8mm 1mm; vertical-align: top; }
        .meta td.label { width: 22%; font-weight: bold; }
        .lines { margin-top: 4mm; }
        .lines th { border-top: 0.8pt solid #000; border-bottom: 0.8pt solid #000; padding: 1mm; text-align: left; }
        .lines td { padding: 0.8mm 1mm; border-bottom: 0.3pt solid #999; }
        .num { text-align: right; }
        .totals { margin-top: 3mm; width: 45%; margin-left: 55%; }
        .totals td { padding: 0.8mm 1mm; }
        .totals tr.payable td { border-top: 0.8pt solid #000; font-weight: bold; font-size: 11pt; }
        .footer { margin-top: 10mm; font-size: 8.5pt; color: #333; }
    </style>
</head>
<body>
    <div class="title">INVOICE</div>
    @if ($isReprint)
        <div class="reprint">REPRINT</div>
    @endif

    <table class="meta">
        <tr>
            <td class="label">Invoice no</td><td>{{ $billing->invoice_no }}</td>
            <td class="label">Date</td><td>{{ $billing->billed_at->format('d/m/Y h:i A') }}</td>
        </tr>
        <tr>
            <td class="label">Patient</td><td>{{ $patient->name }} ({{ $patient->patient_no }})</td>
            <td class="label">Age / Sex</td><td>{{ $age }}{{ $patient->gender ? ' / '.ucfirst($patient->gender->value) : '' }}</td>
        </tr>
        <tr>
            <td class="label">Phone</td><td>{{ $patient->phone }}</td>
            <td class="label">Patient type</td><td>{{ $billing->patientType->name }}</td>
        </tr>
        <tr>
            <td class="label">Ref. by</td>
            <td colspan="3">{{ $referral?->is_self_referral ? 'Self' : $referral?->doctor?->name }}</td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>Sl</th><th>Code</th><th>Test</th><th class="num">Qty</th><th class="num">Rate</th>
                <th class="num">Amount</th><th class="num">Discount</th><th class="num">Net</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($billing->items as $item)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $item->testItem->code }}</td>
                    <td>{{ $item->testItem->name }}</td>
                    <td class="num">{{ $item->quantity }}</td>
                    <td class="num">{{ $taka($item->rate) }}</td>
                    <td class="num">{{ $taka($item->amount) }}</td>
                    <td class="num">{{ $taka($item->discount_amount) }}</td>
                    <td class="num">{{ $taka($item->net_amount) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="num">{{ $taka($billing->subtotal_amount) }}</td></tr>
        <tr><td>Discount{{ $discount ? ' ('.$discount->name.')' : '' }}</td><td class="num">- {{ $taka($billing->discount_amount) }}</td></tr>
        <tr><td>Rounding</td><td class="num">{{ $billing->rounding_amount < 0 ? '- ' : '' }}{{ $taka(abs($billing->rounding_amount)) }}</td></tr>
        <tr class="payable"><td>Payable</td><td class="num">{{ $taka($billing->amount) }}</td></tr>
    </table>

    <div class="footer">
        Billed by {{ $billing->creator?->name }} · Printed by {{ $printedBy }} on {{ $printedAt->format('d/m/Y h:i A') }}
    </div>
</body>
</html>
