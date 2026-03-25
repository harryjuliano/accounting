<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Laporan Arus Kas Tidak Langsung</title>

<style>
    @page { size: A4 landscape; margin: 8mm; }
    body { margin: 0; font-family: "Segoe UI", Arial, sans-serif; color: #111827; font-size: 10px; }
    .toolbar { display: flex; justify-content: flex-end; margin-bottom: 6px; }
    .toolbar button { border: 1px solid #334155; background: #fff; color: #0f172a; padding: 4px 10px; border-radius: 4px; font-size: 10px; cursor: pointer; }
    .report-header { text-align: center; margin-bottom: 8px; }
    .company-name { font-size: 15px; font-weight: 700; margin: 0 0 2px 0; }
    .report-title { font-size: 16px; font-weight: 800; margin: 0; text-transform: uppercase; }
    .report-subtitle { margin-top: 4px; font-size: 10px; color: #374151; }
    .report-meta { margin-top: 8px; display: flex; justify-content: space-between; font-size: 9.5px; color: #374151; border-top: 1px solid #475569; border-bottom: 1px solid #cbd5e1; padding: 5px 0; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 8px; }
    th, td { border: 1px solid #d1d5db; padding: 4px 5px; }
    th { background: #f8fafc; font-size: 9px; text-transform: uppercase; }
    .left-text { text-align: left; }
    .right-text { text-align: right; }
    .section-header td { background: #eef2ff; font-weight: 700; text-transform: uppercase; }
    .subtotal td { background: #f1f5f9; font-weight: 700; }
    .grand-total td { background: #dbeafe; font-weight: 800; }
    .negative { color: #b91c1c; }
    @media print {
        .toolbar { display: none; }
        body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
</head>
<body>
@php
    $months = $report['months'] ?? [];
    $rows = $report['rows'] ?? [];
    $netIncreaseByMonth = $report['netIncreaseByMonth'] ?? [];
    $beginningCashByMonth = $report['beginningCashByMonth'] ?? [];
    $endingCashByMonth = $report['endingCashByMonth'] ?? [];
    $formatAmount = static function ($value) {
        $number = (float) ($value ?? 0);
        $formatted = number_format(abs($number), 0, ',', '.');
        return $number < 0 ? "({$formatted})" : $formatted;
    };
    $sumBySection = static function (array $sourceRows, string $section, int $index): float {
        return collect($sourceRows)
            ->where('section', $section)
            ->sum(fn (array $row) => (float) ($row['values'][$index] ?? 0));
    };
@endphp

<div class="toolbar">
    <button onclick="window.print()">Print / Save PDF</button>
</div>

<div class="report-header">
    <p class="company-name">{{ $report['company']['name'] ?? config('app.name') }}</p>
    <h1 class="report-title">Laporan Arus Kas - Metode Tidak Langsung</h1>
    <div class="report-subtitle">Periode: Januari - Desember {{ $report['filters']['year'] ?? '-' }}</div>
</div>

<div class="report-meta">
    <div><strong>Tahun Fiskal:</strong> {{ $report['filters']['year'] ?? '-' }}</div>
    <div><strong>Status Jurnal:</strong> {{ $report['filters']['status'] ?? '-' }} | <strong>Dicetak:</strong> {{ $generatedAt }}</div>
</div>

<table>
    <thead>
        <tr>
            <th class="left-text" style="width: 22%;">Uraian</th>
            @foreach ($months as $month)
                <th class="right-text">{{ $month['label'] }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach (['operating' => 'Cash Flow from Operating Activities', 'investing' => 'Cash Flow from Investing Activities', 'financing' => 'Cash Flow from Financing Activities'] as $sectionKey => $sectionLabel)
            <tr class="section-header">
                <td colspan="{{ 1 + count($months) }}">{{ $sectionLabel }}</td>
            </tr>
            @foreach ($rows as $row)
                @if (($row['section'] ?? null) === $sectionKey)
                    <tr>
                        <td class="left-text">{{ $row['label'] }}</td>
                        @foreach ($months as $index => $month)
                            @php $value = (float) ($row['values'][$index] ?? 0); @endphp
                            <td class="right-text {{ $value < 0 ? 'negative' : '' }}">{{ $formatAmount($value) }}</td>
                        @endforeach
                    </tr>
                @endif
            @endforeach
            <tr class="subtotal">
                <td class="left-text">Net Cash from {{ str_replace('Cash Flow from ', '', $sectionLabel) }}</td>
                @foreach ($months as $index => $month)
                    @php $sectionTotal = $sumBySection($rows, $sectionKey, $index); @endphp
                    <td class="right-text {{ $sectionTotal < 0 ? 'negative' : '' }}">{{ $formatAmount($sectionTotal) }}</td>
                @endforeach
            </tr>
        @endforeach

        <tr class="grand-total">
            <td class="left-text">Net Increase / (Decrease) in Cash and Cash Equivalents</td>
            @foreach ($months as $index => $month)
                @php $value = (float) ($netIncreaseByMonth[$index] ?? 0); @endphp
                <td class="right-text {{ $value < 0 ? 'negative' : '' }}">{{ $formatAmount($value) }}</td>
            @endforeach
        </tr>
        <tr class="subtotal">
            <td class="left-text">Cash and Cash Equivalents at Beginning of Month</td>
            @foreach ($months as $index => $month)
                @php $value = (float) ($beginningCashByMonth[$index] ?? 0); @endphp
                <td class="right-text {{ $value < 0 ? 'negative' : '' }}">{{ $formatAmount($value) }}</td>
            @endforeach
        </tr>
        <tr class="grand-total">
            <td class="left-text">Cash and Cash Equivalents at End of Month</td>
            @foreach ($months as $index => $month)
                @php $value = (float) ($endingCashByMonth[$index] ?? 0); @endphp
                <td class="right-text {{ $value < 0 ? 'negative' : '' }}">{{ $formatAmount($value) }}</td>
            @endforeach
        </tr>
    </tbody>
</table>

</body>
</html>
