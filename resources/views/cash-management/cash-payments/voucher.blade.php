@php
    $fmt = fn ($amount) => number_format((float) $amount, 0, ',', '.');
    $date = $payment->transaction_date?->format('d/m/Y');
    $terbilang = 'Rupiah';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Payment Voucher {{ $payment->document_no }}</title>
    <style>
        @page { size: A4 landscape; margin: 10mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; font-size: 12px; color: #000; background: #fff; }
        .toolbar { margin: 0 0 8px; text-align: right; }
        .toolbar button { border: 1px solid #333; background: #fff; padding: 6px 12px; cursor: pointer; }
        .voucher { position: relative; border: 2px solid #1f3fff; padding: 10px; width: 100%; min-height: 560px; }
        .watermark { position: absolute; top: 235px; left: 255px; font-size: 86px; color: rgba(70,70,70,.45); z-index: 0; }
        .content { position: relative; z-index: 1; }
        table { border-collapse: collapse; width: 100%; }
        td, th { padding: 3px 5px; vertical-align: top; }
        .yellow { background: #ffff00; }
        .title { background: #dceaf6; text-align: center; font-size: 20px; padding: 2px; }
        .box { border: 1px solid #000; }
        .bt { border-top: 1px solid #000; } .bb { border-bottom: 1px solid #000; } .bl { border-left: 1px solid #000; } .br { border-right: 1px solid #000; }
        .center { text-align: center; } .right { text-align: right; } .italic { font-style: italic; }
        .bee { width: 100px; height: 90px; text-align: center; font-size: 54px; line-height: 1; }
        .sign td { height: 80px; border: 1px solid #000; text-align: center; }
        .line-table th { border: 1px solid #000; font-style: italic; font-weight: 400; }
        .line-table td { border-left: 1px solid #000; border-right: 1px solid #000; border-bottom: 1px dotted #777; height: 20px; }
        .line-table .solid td { border-bottom: 1px solid #000; }
        @media print { .toolbar { display: none; } body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
    </style>
</head>
<body>
<div class="toolbar"><button onclick="window.print()">Print / Save PDF</button></div>
<div class="voucher">
    <div class="watermark">Page 1</div>
    <div class="content">
        <table>
            <tr>
                <td class="bee" rowspan="3">🐝</td>
                <td style="width: 16%"></td>
                <td class="title" colspan="3">Bank Payment Voucher</td>
                <td class="box center" style="width: 22%">Tanggal<br><div class="yellow" style="padding: 6px; font-weight: bold;">{{ $date }}</div></td>
            </tr>
            <tr><td></td><td style="width: 12%">Bank/ Cash</td><td class="yellow" colspan="2">{{ $payment->cashAccount?->account_name ?? '#N/A' }}</td><td></td></tr>
            <tr><td></td><td>Nomor</td><td class="yellow" colspan="2">{{ $payment->document_no }}</td><td></td></tr>
        </table>

        <table style="margin-top: 10px;">
            <tr>
                <td class="box italic" style="width: 20%">Kepada</td>
                <td class="box yellow" style="width: 55%">{{ $payment->counterparty_name ?: '#N/A' }}</td>
                <td class="box center" style="width: 25%">Total Kas Keluar</td>
            </tr>
            <tr>
                <td class="bl italic">Keterangan</td>
                <td class="yellow br">{{ $payment->description ?: '#N/A' }}</td>
                <td class="box"><span style="float:left">Rp</span><span style="float:right">{{ $fmt($payment->amount) }}</span></td>
            </tr>
            <tr><td class="bb bl"></td><td class="bb br yellow" style="height: 25px;"></td><td></td></tr>
        </table>

        <table style="margin-top: 18px; width: 100%;">
            <tr><td class="italic" style="width: 24%">Cheque / Notes No</td><td class="yellow" style="width: 22%">{{ $payment->reference_no }}</td><td></td></tr>
            <tr><td class="italic">Terbilang</td><td class="box" colspan="2" style="height: 34px;">{{ $terbilang }}</td></tr>
        </table>

        <table class="line-table" style="margin-top: 20px;">
            <thead><tr><th style="width: 6%">No</th><th style="width: 38%">Kode Transaksi</th><th style="width: 21%">Ammount</th><th>Refference</th></tr></thead>
            <tbody>
            @foreach(range(0, max(4, $payment->paymentLines->count() - 1)) as $index)
                @php $line = $payment->paymentLines[$index] ?? null; @endphp
                <tr>
                    <td class="center yellow">{{ $index + 1 }}</td>
                    <td class="yellow">{{ $line?->transaction_code ?? $line?->debitAccount?->code }}</td>
                    <td class="right yellow">{{ $line ? $fmt($line->amount) : '' }}</td>
                    <td class="yellow">{{ $line?->reference_no }}</td>
                </tr>
            @endforeach
                <tr class="solid"><td colspan="2"></td><td class="right">{{ $fmt($payment->amount) }}</td><td></td></tr>
            </tbody>
        </table>

        <table class="sign" style="margin-top: 20px;">
            <tr><td>Posted by</td><td>Approved by</td><td>Checked by</td><td>Prepared by</td><td>Received by</td></tr>
            <tr><td></td><td></td><td></td><td></td><td></td></tr>
            <tr><td class="right" style="height: 24px; text-align:left;">Date&nbsp;&nbsp;:</td><td></td><td></td><td></td><td></td></tr>
        </table>
        <div style="margin-top: 8px; font-size: 10px; color: #555;">Journal: {{ $payment->journalEntry?->journal_no ?? '-' }} | Debit: multi-line COA | Kredit: {{ $payment->cashAccount?->glAccount?->code }} - {{ $payment->cashAccount?->glAccount?->name }}</div>
    </div>
</div>
</body>
</html>
