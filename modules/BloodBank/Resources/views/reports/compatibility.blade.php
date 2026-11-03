{{--
    Compatibility Test Report, one page per unit, laid out like the paper report. Printed on letterhead paper
    (215 × 292 mm): the top 35 mm hold the pre-printed header, so nothing is drawn there. Rendered by mPDF
    (CompatibilityReportBuilder); keep to tables and simple CSS, which is what mPDF lays out reliably.
--}}
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: freeserif; font-size: 11pt; color: #000; }
        table { border-collapse: collapse; width: 100%; }
        td { vertical-align: middle; }
        .b { font-weight: bold; }

        .patient { border: 0.8pt solid #000; }
        .patient td { font-weight: bold; padding: 1.2mm 1.5mm; white-space: nowrap; }

        .title { text-align: center; font-size: 15pt; font-weight: bold; margin: 4.5mm 0 3.5mm; }

        .bag { margin-top: 0; }
        .bag td { border: 0.8pt solid #000; font-weight: bold; padding: 0.5mm 1.5mm; white-space: nowrap; }

        .heading { font-size: 10.5pt; font-weight: bold; text-decoration: underline; margin-top: 2mm; }
        .detail td { border: 0.8pt solid #000; padding: 0.6mm 1.5mm; }
        .detail td.label { width: 39.5%; font-weight: bold; }

        .screening { margin-top: 2.5mm; }
        .screening th { border-top: 0.8pt solid #000; border-bottom: 0.8pt solid #000; font-size: 12pt; font-weight: bold; text-align: left; padding: 0.5mm 1.5mm; }
        .screening th.first { border-left: 0.8pt solid #000; }
        .screening th.last { border-right: 0.8pt solid #000; text-align: center; }
        .screening td { padding: 0.4mm 1.5mm; }
        .screening td.name { width: 31%; font-weight: bold; }
        .screening td.method { width: 38%; }
        .screening td.result { text-align: center; }

        .compatibility { margin-top: 1.5mm; }
        .compatibility td { border: 0.8pt solid #000; padding: 0.6mm 1.5mm; }
        .compatibility td.label { width: 39.5%; font-weight: bold; }

        .instructions { font-family: freesans; font-size: 10.5pt; margin-top: 0; }
        .instructions td { padding: 0.25mm 0; vertical-align: top; }
        .instructions td.no { width: 9mm; text-align: right; padding-right: 2.5mm; }

        .signatures { margin-top: 10mm; font-family: freesans; font-size: 10.5pt; page-break-inside: avoid; }
        .signatures td { vertical-align: top; padding-top: 1mm; }
        .signatures td.sign { border-top: 0.8pt solid #000; }

        .footer { font-family: freesans; font-size: 10pt; }
    </style>
</head>
<body>
<htmlpagefooter name="ctr">
    <table class="footer"><tr>
        <td style="width: 33%;"></td>
        <td style="width: 34%; text-align: center;">Page <b>{PAGENO}</b> of <b>{nbpg}</b></td>
        <td style="width: 33%;"></td>
    </tr></table>
</htmlpagefooter>
<htmlpagefooter name="ctrReprint">
    <table class="footer"><tr>
        <td style="width: 33%;"></td>
        <td style="width: 34%; text-align: center;">Page <b>{PAGENO}</b> of <b>{nbpg}</b></td>
        <td style="width: 33%; text-align: right; font-size: 8pt;">Reprint</td>
    </tr></table>
</htmlpagefooter>

@foreach ($pages as $page)
    @if (! $loop->first)
        <pagebreak resetpagenum="1" />
    @endif
    <sethtmlpagefooter name="{{ $page['is_reprint'] ? 'ctrReprint' : 'ctr' }}" value="on" />

    <table class="patient">
        <tr>
            <td style="width: 16%;">Patient Name:</td>
            <td style="width: 25%;">{{ $page['patient']['name'] }}</td>
            <td style="width: 14%;">Gender:</td>
            <td style="width: 17%;">{{ $page['patient']['gender'] }}</td>
            <td style="width: 13%;">Age:</td>
            <td style="width: 15%;">{{ $page['patient']['age'] }}</td>
        </tr>
        <tr>
            <td>Invoice:</td>
            <td>{{ $page['invoice_no'] }}</td>
            <td>Invoice Date:</td>
            <td>{{ $page['invoice_date'] }}</td>
            <td>Result Date:</td>
            <td>{{ $page['result_date'] }}</td>
        </tr>
        <tr>
            <td>Contact No:</td>
            <td>{{ $page['patient']['phone'] }}</td>
            <td>Ref. by:</td>
            <td colspan="3" style="white-space: normal;">{{ $page['referred_by'] }}</td>
        </tr>
    </table>

    <div class="title">Compatibility Test Report</div>

    <table class="bag">
        <tr>
            <td style="width: 33.6%;">Blood Bag No.: {{ $page['bag_no'] }}</td>
            <td style="width: 28.4%;">Unit: {{ $page['unit_sequence'] }}</td>
            <td style="width: 38%;">Component: {{ $page['component'] }}</td>
        </tr>
    </table>
    <table class="bag">
        <tr>
            <td style="width: 20.5%;">Date of collection-</td>
            <td style="width: 13.3%;">{{ $page['collected_at'] }}</td>
            <td style="width: 14.6%;">Date of expiry</td>
            <td style="width: 18.3%;">{{ $page['expires_at'] }}</td>
            <td style="width: 33.3%;">Collection by- {{ $page['collected_by'] }}</td>
        </tr>
    </table>

    <div class="heading">PATIENT&rsquo;S Detail</div>
    <table class="detail">
        <tr><td class="label">Blood Group (ABO)</td><td>@if ($page['patient']['abo'])&ldquo;{{ $page['patient']['abo'] }}&rdquo;@endif</td></tr>
        <tr><td class="label">Rhesus (D)</td><td>{{ $page['patient']['rh'] }}</td></tr>
        <tr><td class="label">Cause of transfusion</td><td>{{ $page['transfusion_reason'] }}</td></tr>
    </table>

    <div class="heading">DONOR&rsquo;S Details</div>
    <table class="detail">
        <tr><td class="label">Name</td><td>{{ $page['donor']['name'] }}</td></tr>
        <tr><td class="label">Age</td><td>{{ $page['donor']['age'] }}</td></tr>
        <tr><td class="label">Sex</td><td>{{ $page['donor']['gender'] }}</td></tr>
        <tr><td class="label">Blood Group (ABO)</td><td>@if ($page['donor']['abo'])&ldquo;{{ $page['donor']['abo'] }}&rdquo;@endif</td></tr>
        <tr><td class="label">Rhesus (D)</td><td>{{ $page['donor']['rh'] }}</td></tr>
    </table>

    <table class="screening">
        <tr>
            <th class="first">Screening Tests</th>
            <th>Method</th>
            <th class="last">Result</th>
        </tr>
        @foreach ($page['screening'] as $test)
            <tr>
                <td class="name">{{ $test['name'] }}</td>
                <td class="method">{{ $test['method'] }}</td>
                <td class="result">{{ $test['result'] }}</td>
            </tr>
        @endforeach
    </table>

    <div class="heading" style="margin-top: 1mm;">COMMENT</div>
    @if ($page['remarks'])
        <div>{{ $page['remarks'] }}</div>
    @endif
    <table class="compatibility">
        <tr><td class="label">Compatibility with donor</td><td>{{ $page['compatibility'] }}</td></tr>
    </table>

    <div class="heading" style="font-size: 11.5pt; margin-top: 2mm;">Instructions:</div>
    <table class="instructions">
        <tr><td class="no">1.</td><td>Please don&rsquo;t add any medicine to blood unit.</td></tr>
        <tr><td class="no">2.</td><td>Use only standard Blood Transfusion filter set for Blood Transfusion.</td></tr>
        <tr><td class="no">3.</td><td>Check for color, clot formation, if anything abnormal please contact with Blood Bank.</td></tr>
        <tr><td class="no">4.</td><td>Platelet &amp; FFP once issued cannot be received back and will be discarded.</td></tr>
        <tr><td class="no b">5.</td><td class="b">If not required, please return the blood bag (Whole Blood/PRBC) within 30 min of issue to blood bank with reason &amp; signature of doctor.</td></tr>
    </table>

    <table class="signatures">
        <tr>
            <td style="width: 5%;"></td>
            <td class="sign" style="width: 23%;">&nbsp;Medical Technologist</td>
            <td style="width: 46%;"></td>
            <td class="sign" style="width: 11%;">&nbsp;Physician</td>
            <td style="width: 15%;"></td>
        </tr>
    </table>
@endforeach
</body>
</html>
