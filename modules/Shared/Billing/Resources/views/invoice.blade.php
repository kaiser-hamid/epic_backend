{{--
    Counter bill, laid out like the paper bill. Printed on the pre-printed pad (A5, 148 × 210 mm): the top and bottom
    27 mm hold the pad's own header and QR footer, so nothing is drawn there. One print is the Customer Copy, then
    one Non Lab Copy per department. Rendered by mPDF (InvoicePrintBuilder); keep to tables and simple CSS, which
    is what mPDF lays out reliably. All values arrive formatted.
--}}
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: freesans; font-size: 8.5pt; color: #000; }
        table { border-collapse: collapse; width: 100%; }
        td, th { vertical-align: middle; padding: 0; }
        .b { font-weight: bold; }
        .right { text-align: right; }
        .center { text-align: center; }

        .title td { padding-bottom: 0.8mm; }
        .title .copy { font-size: 13pt; font-weight: bold; text-align: center; }
        .title .printed { font-size: 7.5pt; font-style: italic; text-align: right; }

        .codes td { padding-bottom: 1.5mm; }
        .codes .date { font-size: 9pt; font-weight: bold; text-align: center; white-space: nowrap; }
        .barcode { padding: 0; margin: 0; }

        .info td { padding: 0.5mm 0; vertical-align: top; }
        .info .label { white-space: nowrap; }
        .info .colon { width: 3mm; text-align: center; }
        .info .key { font-size: 10pt; }
        .info .status { font-size: 10pt; font-weight: bold; text-align: right; white-space: nowrap; }

        .department { font-size: 12.5pt; font-weight: bold; text-align: center; margin: 2mm 0 1mm; }

        .items { margin-top: 2mm; }
        .items th { font-weight: bold; text-align: left; padding: 0.4mm 0; border-bottom: 0.5pt solid #000; }
        .items td { padding: 0.3mm 0; vertical-align: top; }
        .items .code { width: 15%; }
        .items .price { width: 20%; text-align: right; }

        .remarks { border-top: 0.5pt solid #000; padding-top: 0.8mm; margin-top: 0.5mm; }

        .bottom { margin-top: 2mm; page-break-inside: avoid; }
        .bottom td { vertical-align: top; }
        .delivery { font-size: 9pt; width: auto; }
        .delivery .label { white-space: nowrap; }
        .delivery .value { font-size: 10.5pt; font-weight: bold; padding-left: 2mm; }

        .stamp { font-size: 16pt; font-weight: bold; text-align: center; background-color: #ddd; padding: 0.5mm 3mm; }

        .totals td { padding: 0.5mm 0; text-align: right; }
        .totals .amount { width: 38%; }
        .totals tr.line td { border-bottom: 0.5pt solid #000; }
        .totals tr.due td { font-size: 10pt; font-weight: bold; }

        .words { margin-top: 1.5mm; }

        .collections { margin-top: 3mm; width: 72%; }
        .collections td { border: 0.5pt solid #000; padding: 0.2mm 1mm; font-size: 7.5pt; }

        .prepared { margin-top: 3mm; }
        .prepared td { padding: 0.5mm 0; }
    </style>
</head>
<body>
@php
    $copies = [['title' => 'Customer Copy', 'department' => null, 'items' => $customerCopy['items'], 'withPrice' => true]];

    foreach ($departmentCopies as $departmentCopy) {
        $copies[] = ['title' => 'Non Lab Copy', 'department' => $departmentCopy['department'], 'items' => $departmentCopy['items'], 'withPrice' => false];
    }
@endphp

@foreach ($copies as $copy)
    @if (! $loop->first)
        <pagebreak />
    @endif

    <table class="title">
        <tr>
            <td style="width: 30%;"></td>
            <td class="copy" style="width: 40%;">{{ $copy['title'] }}</td>
            <td class="printed" style="width: 30%;">{{ $header['printed_at'] }}</td>
        </tr>
    </table>

    <table class="codes">
        <tr>
            <td style="width: 38%;"><barcode code="{{ $header['invoice_no'] }}" type="C128B" size="0.62" height="1" class="barcode" /></td>
            <td class="date" style="width: 24%;">Date : {{ $header['billed_at'] }}</td>
            <td class="right" style="width: 38%;"><barcode code="{{ $header['patient_no'] }}" type="C128B" size="0.62" height="1" class="barcode" /></td>
        </tr>
    </table>

    <table class="info">
        <tr>
            <td class="label key" style="width: 14%;">Bill No.</td>
            <td class="colon key">:</td>
            <td class="key b" style="width: 40%;">{{ $header['invoice_no'] }}</td>
            <td class="key right" colspan="4">ULID No. : <b>{{ $header['patient_no'] }}</b></td>
        </tr>
        <tr>
            <td class="label key">Name</td>
            <td class="colon key">:</td>
            <td class="key" colspan="3">{{ $header['name'] }}</td>
            <td class="status" colspan="2">{{ $header['copy_status'] }}</td>
        </tr>
        <tr>
            <td class="label">Age</td>
            <td class="colon">:</td>
            <td>
                {{ $header['age'] }}
                @if ($header['gender'])
                    &nbsp;&nbsp;&nbsp;&nbsp;Gender : {{ $header['gender'] }}
                @endif
            </td>
            <td class="right" colspan="4">
                @if ($header['phone'])
                    Contact No. : {{ $header['phone'] }}
                @endif
            </td>
        </tr>
        <tr>
            <td class="label">Ref. By</td>
            <td class="colon">:</td>
            <td colspan="4">{{ $header['referred_by'] }}</td>
            <td class="right" style="width: 12%;">{{ $header['staff_code'] }}</td>
        </tr>
    </table>

    @if ($copy['department'])
        <div class="department">Department : {{ $copy['department'] }}</div>
    @endif

    <table class="items">
        <tr>
            <th class="code">Code</th>
            <th>Test Name</th>
            @if ($copy['withPrice'])
                <th class="price">Price (Tk.)</th>
            @endif
        </tr>
        @foreach ($copy['items'] as $item)
            <tr>
                <td class="code">{{ $item['code'] }}</td>
                <td>{{ $item['name'] }}</td>
                @if ($copy['withPrice'])
                    <td class="price">{{ $item['price'] }}</td>
                @endif
            </tr>
        @endforeach
    </table>
    <div class="remarks">Remarks :: {{ $header['remarks'] }}</div>

    @if ($copy['withPrice'])
        <table class="bottom">
            <tr>
                <td style="width: 55%;">
                    <table class="delivery">
                        <tr><td class="b label">Delivery Date :</td><td class="value">{{ $header['delivery_at'] }}</td></tr>
                    </table>
                    <table style="margin-top: 2.5mm;">
                        <tr>
                            <td style="width: 22%;"></td>
                            <td style="width: 40%;" class="stamp">{{ $header['is_paid'] ? 'PAID' : 'DUE' }}</td>
                            <td></td>
                        </tr>
                    </table>
                </td>
                <td style="width: 45%;">
                    <table class="totals">
                        <tr><td>Item Total Tk.</td><td class="amount">{{ $customerCopy['item_total'] }}</td></tr>
                        <tr class="line"><td>(-)Discount Tk.</td><td class="amount">{{ $customerCopy['discount'] }}</td></tr>
                        @if ($customerCopy['rounding'] !== null)
                            <tr class="line"><td>Rounding Tk.</td><td class="amount">{{ $customerCopy['rounding'] }}</td></tr>
                        @endif
                        <tr><td>Net Payable Tk.</td><td class="amount">{{ $customerCopy['payable'] }}</td></tr>
                        <tr class="line"><td>Total Payment Tk.</td><td class="amount">{{ $customerCopy['paid'] }}</td></tr>
                        <tr class="due"><td>Due Tk.</td><td class="amount">{{ $customerCopy['due'] }}</td></tr>
                    </table>
                </td>
            </tr>
        </table>
        <div class="words">In Word: {{ $customerCopy['in_words'] }}</div>

        @if ($customerCopy['initial_collection'])
            <table class="collections">
                <tr>
                    <td style="width: 30%;">Initial Collection</td>
                    <td>{{ $customerCopy['initial_collection']['by'] }}</td>
                    <td class="right" style="width: 25%;">{{ $customerCopy['initial_collection']['amount'] }}</td>
                </tr>
            </table>
        @endif
    @else
        <table class="bottom">
            <tr>
                <td style="width: 55%;">
                    <table class="delivery">
                        <tr><td class="b label">Delivery Date :</td><td class="value">{{ $header['delivery_at'] }}</td></tr>
                    </table>
                </td>
                <td style="width: 45%;"></td>
            </tr>
        </table>
        <table style="margin-top: 2.5mm;">
            <tr>
                <td style="width: 35%;"></td>
                <td style="width: 22%;" class="stamp">{{ $header['is_paid'] ? 'PAID' : 'DUE' }}</td>
                <td></td>
            </tr>
        </table>
    @endif

    <table class="prepared">
        <tr><td style="width: 22%;">Prepared By ::</td><td>{{ $header['prepared_by'] }}</td></tr>
    </table>
@endforeach
</body>
</html>
