<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Laporan Neraca</title>

<style>
    @page {
        size: A4 portrait;
        margin: 14mm 16mm;
    }

    :root{
        --text:#111827;
        --muted:#6b7280;
        --line:#cfd6df;
        --line-strong:#4b5563;
        --header:#0f172a;
        --soft:#f8fafc;
        --subtotal:#eef4ff;
        --grand:#e2ecff;
        --negative:#c62828;
    }

    * { box-sizing: border-box; }
    body {
        margin: 0;
        font-family: "Segoe UI", Arial, sans-serif;
        color: var(--text);
        font-size: 11px;
        background: #fff;
    }

    .toolbar { display: flex; justify-content: flex-end; margin-bottom: 10px; }
    .toolbar button {
        border: 1px solid #334155;
        background: #fff;
        color: #0f172a;
        padding: 6px 12px;
        border-radius: 4px;
        font-size: 11px;
        cursor: pointer;
    }
    .report-header { text-align: center; margin-bottom: 12px; }
    .company-name { font-size: 16px; font-weight: 700; margin: 0 0 2px 0; color: var(--header); letter-spacing: .2px; }
    .report-title { font-size: 20px; font-weight: 800; margin: 0; color: var(--header); text-transform: uppercase; letter-spacing: .4px; }
    .report-subtitle { margin-top: 6px; font-size: 11px; color: #374151; }

    .report-meta {
        margin-top: 10px;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 20px;
        font-size: 10.5px;
        color: #374151;
        border-top: 1px solid var(--line-strong);
        border-bottom: 1px solid var(--line);
        padding: 7px 0;
    }

    table.report-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        margin-top: 10px;
    }

    .report-table col.desc { width: 31%; }
    .report-table col.seg { width: 13%; }
    .report-table col.amt { width: 14%; }
    .report-table col.pct { width: 7%; }

    .report-table thead th {
        padding: 7px 6px;
        border-bottom: 1px solid var(--line-strong);
        color: #111827;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: .35px;
        background: #fff;
    }

    .report-table td {
        padding: 5px 6px;
        border-bottom: 0.7px solid #e5e7eb;
        vertical-align: middle;
        font-size: 11px;
    }

    .left-text { text-align: left; }
    .right-text { text-align: right; }
    .section td {
        padding-top: 10px;
        padding-bottom: 6px;
        border-bottom: none;
        font-weight: 800;
        text-transform: uppercase;
        color: #0f172a;
    }

    .detail td:first-child { padding-left: 18px; }
    .subtotal td {
        font-weight: 700;
        background: var(--subtotal);
        border-top: 1px solid #94a3b8;
        border-bottom: 1px solid #94a3b8;
    }

    .grand-total td {
        font-weight: 800;
        background: var(--grand);
        border-top: 2px solid #334155;
        border-bottom: 2px solid #334155;
    }

    .negative { color: var(--negative); }

    @media print {
        .toolbar { display: none; }
        body {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }
</style>
</head>
<body>
@php
    $currentYear = (int) $filters['year'];
    $previousYear = $currentYear - 1;
    $periodDate = \Carbon\Carbon::create($currentYear, (int) $filters['period'], 1)->locale('id')->endOfMonth();
    $periodLabel = $periodDate->translatedFormat('F Y');
    $printedDate = \Carbon\Carbon::parse($generatedAt)->locale('id')->translatedFormat('d F Y');

    $formatAmount = function ($value) {
        $number = number_format(abs((float) $value), 0, ',', '.');
        return (float) $value < 0 ? '(' . $number . ')' : $number;
    };
    $formatPercent = fn ($value) => number_format((float) $value, 2, ',', '.') . '%';

    $rowsBySegment = collect($rows)->groupBy('segment_key');
    $segmentLabels = [
        'asset' => 'Asset',
        'liability' => 'Liability',
        'equity' => 'Equity',
        'current_year_profit' => 'Current Year Profit',
    ];

    $renderLabel = fn ($row) => $row['coa_level_3'] ?? $row['coa_level_2'] ?? $row['coa_level_1'] ?? '-';

    $totalAssetCurrent = (float) ($summary['total_asset_current_year'] ?? 0);
    $totalAssetPrevious = (float) ($summary['total_asset_previous_year'] ?? 0);
    $totalAssetVariance = (float) ($summary['total_asset_variance'] ?? 0);
    $totalRightCurrent = (float) ($summary['total_right_side_current_year'] ?? 0);
    $totalRightPrevious = (float) ($summary['total_right_side_previous_year'] ?? 0);
    $totalRightVariance = $totalRightCurrent - $totalRightPrevious;

    $balanceCurrent = $totalAssetCurrent - $totalRightCurrent;
    $balancePrevious = $totalAssetPrevious - $totalRightPrevious;
    $balanceVariance = $totalAssetVariance - $totalRightVariance;
@endphp

<div class="page">
    <div class="toolbar">
        <button onclick="window.print()">Print / Save PDF</button>
    </div>

    <div class="report-header">
        <p class="company-name">{{ auth()->user()?->company?->name ?? 'Company' }}</p>
        <h1 class="report-title">Laporan Neraca</h1>
        <p class="report-subtitle">Periode: {{ ucfirst($periodLabel) }}</p>
    </div>

    <div class="report-meta">
        <div class="left">
            <div><strong>Tahun Berjalan:</strong> {{ $currentYear }}</div>
            <div><strong>Tahun Pembanding:</strong> {{ $previousYear }}</div>
            <div><strong>COA Ditampilkan:</strong> Level 3</div>
        </div>
        <div class="right" style="text-align:right;">
            <div><strong>Status Jurnal:</strong> {{ strtoupper($filters['status'] ?? 'POSTED') }}</div>
            <div><strong>Dicetak:</strong> {{ $printedDate }}</div>
        </div>
    </div>

    <table class="report-table">
        <colgroup>
            <col class="seg">
            <col class="desc">
            <col class="amt">
            <col class="pct">
            <col class="amt">
            <col class="pct">
            <col class="amt">
            <col class="pct">
        </colgroup>
        <thead>
            <tr>
                <th class="left-text">Segment</th>
                <th class="left-text">COA Level 3</th>
                <th class="right-text">{{ $currentYear }}</th>
                <th class="right-text">% Asset</th>
                <th class="right-text">{{ $previousYear }}</th>
                <th class="right-text">% Asset</th>
                <th class="right-text">Variance</th>
                <th class="right-text">% Asset</th>
            </tr>
        </thead>
        <tbody>
            @foreach (['asset', 'liability', 'equity', 'current_year_profit'] as $segment)
                @php $segmentRows = $rowsBySegment->get($segment, collect()); @endphp
                @if($segmentRows->isNotEmpty())
                    <tr class="section">
                        <td colspan="8">{{ $segmentLabels[$segment] ?? $segment }}</td>
                    </tr>
                    @foreach($segmentRows as $row)
                        <tr class="detail">
                            <td class="left-text">{{ $segmentLabels[$segment] ?? '-' }}</td>
                            <td class="left-text">{{ $renderLabel($row) }}</td>
                            <td class="right-text {{ (float)$row['current_year'] < 0 ? 'negative' : '' }}">{{ $formatAmount($row['current_year']) }}</td>
                            <td class="right-text">{{ $formatPercent($row['current_year_percent_asset']) }}</td>
                            <td class="right-text {{ (float)$row['previous_year'] < 0 ? 'negative' : '' }}">{{ $formatAmount($row['previous_year']) }}</td>
                            <td class="right-text">{{ $formatPercent($row['previous_year_percent_asset']) }}</td>
                            <td class="right-text {{ (float)$row['variance'] < 0 ? 'negative' : '' }}">{{ $formatAmount($row['variance']) }}</td>
                            <td class="right-text">{{ $formatPercent($row['variance_percent_asset']) }}</td>
                        </tr>
                    @endforeach
                @endif
            @endforeach

            <tr class="subtotal">
                <td class="left-text">Asset</td>
                <td class="left-text">Total Asset</td>
                <td class="right-text {{ $totalAssetCurrent < 0 ? 'negative' : '' }}">{{ $formatAmount($totalAssetCurrent) }}</td>
                <td class="right-text">100,00%</td>
                <td class="right-text {{ $totalAssetPrevious < 0 ? 'negative' : '' }}">{{ $formatAmount($totalAssetPrevious) }}</td>
                <td class="right-text">100,00%</td>
                <td class="right-text {{ $totalAssetVariance < 0 ? 'negative' : '' }}">{{ $formatAmount($totalAssetVariance) }}</td>
                <td class="right-text">100,00%</td>
            </tr>

            <tr class="subtotal">
                <td class="left-text">Liability + Equity + Profit</td>
                <td class="left-text">Total Right Side</td>
                <td class="right-text {{ $totalRightCurrent < 0 ? 'negative' : '' }}">{{ $formatAmount($totalRightCurrent) }}</td>
                <td class="right-text">{{ $formatPercent(abs($totalAssetCurrent) > 0.000001 ? ($totalRightCurrent / $totalAssetCurrent) * 100 : 0) }}</td>
                <td class="right-text {{ $totalRightPrevious < 0 ? 'negative' : '' }}">{{ $formatAmount($totalRightPrevious) }}</td>
                <td class="right-text">{{ $formatPercent(abs($totalAssetPrevious) > 0.000001 ? ($totalRightPrevious / $totalAssetPrevious) * 100 : 0) }}</td>
                <td class="right-text {{ $totalRightVariance < 0 ? 'negative' : '' }}">{{ $formatAmount($totalRightVariance) }}</td>
                <td class="right-text">{{ $formatPercent(abs($totalAssetVariance) > 0.000001 ? ($totalRightVariance / $totalAssetVariance) * 100 : 0) }}</td>
            </tr>

            <tr class="grand-total">
                <td class="left-text">Balance</td>
                <td class="left-text">Asset - Right Side</td>
                <td class="right-text {{ $balanceCurrent < 0 ? 'negative' : '' }}">{{ $formatAmount($balanceCurrent) }}</td>
                <td class="right-text">{{ $formatPercent(abs($totalAssetCurrent) > 0.000001 ? ($balanceCurrent / $totalAssetCurrent) * 100 : 0) }}</td>
                <td class="right-text {{ $balancePrevious < 0 ? 'negative' : '' }}">{{ $formatAmount($balancePrevious) }}</td>
                <td class="right-text">{{ $formatPercent(abs($totalAssetPrevious) > 0.000001 ? ($balancePrevious / $totalAssetPrevious) * 100 : 0) }}</td>
                <td class="right-text {{ $balanceVariance < 0 ? 'negative' : '' }}">{{ $formatAmount($balanceVariance) }}</td>
                <td class="right-text">{{ $formatPercent(abs($totalAssetVariance) > 0.000001 ? ($balanceVariance / $totalAssetVariance) * 100 : 0) }}</td>
            </tr>
        </tbody>
    </table>
</div>
</body>
</html>
