<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Laporan Laba Rugi</title>

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

    .page {
        width: 100%;
        max-width: 100%;
        margin: 0 auto;
        padding: 0;
    }

    .toolbar {
        display: flex;
        justify-content: flex-end;
        margin-bottom: 10px;
    }

    .toolbar button {
        border: 1px solid #334155;
        background: #fff;
        color: #0f172a;
        padding: 6px 12px;
        border-radius: 4px;
        font-size: 11px;
        cursor: pointer;
    }

    .toolbar button:hover { background: #f8fafc; }

    .report-header { text-align: center; margin-bottom: 12px; }
    .company-name {
        font-size: 16px;
        font-weight: 700;
        margin: 0 0 2px 0;
        color: var(--header);
        letter-spacing: .2px;
    }

    .report-title {
        font-size: 20px;
        font-weight: 800;
        margin: 0;
        color: var(--header);
        text-transform: uppercase;
        letter-spacing: .4px;
    }

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

    .report-meta .left, .report-meta .right { width: 50%; }
    .report-meta .right { text-align: right; }

    table.report-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        margin-top: 10px;
    }

    .report-table col.desc   { width: 26%; }
    .report-table col.amt    { width: 12%; }
    .report-table col.pct    { width: 7%; }

    .report-table thead th {
        padding: 7px 6px;
        border-bottom: 1px solid var(--line-strong);
        color: #111827;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: .35px;
        background: #fff;
    }

    .report-table thead tr.group-header th {
        font-size: 10px;
        font-weight: 700;
        color: #0f172a;
        border-top: 1.5px solid var(--line-strong);
        border-bottom: 1px solid var(--line);
        padding-top: 8px;
        padding-bottom: 8px;
    }

    .report-table thead tr.sub-header th {
        font-weight: 700;
        color: #475569;
        border-bottom: 1.5px solid var(--line-strong);
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
        background: #fff;
    }

    .detail td:first-child { padding-left: 18px; }

    .subtotal td {
        font-weight: 700;
        background: var(--subtotal);
        border-top: 1px solid #94a3b8;
        border-bottom: 1px solid #94a3b8;
    }

    .gross-profit td {
        font-weight: 800;
        background: #f8fbff;
        border-top: 1.4px solid #64748b;
        border-bottom: 1.4px solid #64748b;
    }

    .grand-total td {
        font-weight: 800;
        background: var(--grand);
        border-top: 2px solid #334155;
        border-bottom: 2px solid #334155;
        font-size: 11.5px;
    }

    .negative { color: var(--negative); }
    .positive-variance { color: #0f766e; }
    .negative-variance { color: var(--negative); }

    .footer {
        margin-top: 18px;
        padding-top: 8px;
        border-top: 1px solid var(--line);
        display: flex;
        justify-content: space-between;
        gap: 20px;
        font-size: 10px;
        color: var(--muted);
    }

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
    $varianceClass = fn ($value) => (float) $value >= 0 ? 'positive-variance' : 'negative-variance';
    $safePercent = fn ($numerator, $denominator) => abs((float) $denominator) > 0.000001
        ? (((float) $numerator / (float) $denominator) * 100)
        : 0;

    $rowsByGroup = collect($rows)->groupBy('account_group_type');
    $revenueRows = $rowsByGroup->get('revenue', collect());
    $cogsRows = $rowsByGroup->get('cogs', collect());
    $operationalRows = $rowsByGroup->get('expense', collect());
    $otherRows = $rowsByGroup->get('other_income', collect())->concat($rowsByGroup->get('other_expense', collect()));

    $subtotalRows = [
        ['label' => 'Total Pendapatan', 'key' => 'total_sales', 'class' => 'subtotal'],
        ['label' => 'Total HPP', 'key' => 'total_cogs', 'class' => 'subtotal'],
        ['label' => 'Gross Profit', 'key' => 'gross_profit', 'class' => 'gross-profit'],
        ['label' => 'Total Biaya Operasional', 'key' => 'total_operating_expense', 'class' => 'subtotal'],
        ['label' => 'Net Profit Before Tax', 'key' => 'net_profit_before_tax', 'class' => 'subtotal'],
        ['label' => 'Pajak Penghasilan', 'key' => 'income_tax', 'class' => 'detail'],
        ['label' => 'Net Profit After Tax', 'key' => 'net_profit_after_tax', 'class' => 'grand-total'],
    ];

    $rowLabel = fn ($row) => $row['coa_level_3'] ?? $row['coa_level_2'] ?? $row['coa_level_1'] ?? '-';
@endphp

<div class="page">
    <div class="toolbar">
        <button onclick="window.print()">Print</button>
    </div>

    <div class="report-header">
        <p class="company-name">{{ $companyProfile['legal_name'] ?: $companyProfile['name'] }}</p>
        <h1 class="report-title">Laporan Laba Rugi</h1>
        <div class="report-subtitle">Periode: {{ ucfirst($periodLabel) }} | Perbandingan {{ $currentYear }} vs {{ $previousYear }}</div>
    </div>

    <div class="report-meta">
        <div class="left">
            Status Jurnal: <strong>{{ strtoupper($filters['status']) }}</strong><br>
            Mata Uang: <strong>{{ $companyProfile['base_currency_code'] ?: 'IDR' }}</strong>
        </div>
        <div class="right">
            Dicetak: <strong>{{ $printedDate }}</strong><br>
            Cabang: <strong>{{ $companyProfile['branch_name'] ?: 'Semua Cabang' }}</strong>
        </div>
    </div>

    <table class="report-table">
        <colgroup>
            <col class="desc">
            <col class="amt">
            <col class="pct">
            <col class="amt">
            <col class="pct">
            <col class="amt">
            <col class="pct">
        </colgroup>

        <thead>
            <tr class="group-header">
                <th rowspan="2" class="left-text">Uraian (COA Level 3)</th>
                <th colspan="2" class="right-text">{{ $currentYear }}</th>
                <th colspan="2" class="right-text">{{ $previousYear }}</th>
                <th colspan="2" class="right-text">Variance {{ $currentYear }} vs {{ $previousYear }}</th>
            </tr>
            <tr class="sub-header">
                <th class="right-text">Amount</th>
                <th class="right-text">%</th>
                <th class="right-text">Amount</th>
                <th class="right-text">%</th>
                <th class="right-text">Amount</th>
                <th class="right-text">%</th>
            </tr>
        </thead>

        <tbody>
            <tr class="section"><td colspan="7">Pendapatan</td></tr>
            @foreach($revenueRows as $row)
                <tr class="detail">
                    <td class="left-text">{{ $rowLabel($row) }}</td>
                    <td class="right-text {{ (float) $row['current_year'] < 0 ? 'negative' : '' }}">{{ $formatAmount($row['current_year']) }}</td>
                    <td class="right-text {{ (float) $row['current_year_percent_sales'] < 0 ? 'negative' : '' }}">{{ $formatPercent($row['current_year_percent_sales']) }}</td>
                    <td class="right-text {{ (float) $row['previous_year'] < 0 ? 'negative' : '' }}">{{ $formatAmount($row['previous_year']) }}</td>
                    <td class="right-text {{ (float) $row['previous_year_percent_sales'] < 0 ? 'negative' : '' }}">{{ $formatPercent($row['previous_year_percent_sales']) }}</td>
                    <td class="right-text {{ $varianceClass($row['variance']) }}">{{ $formatAmount($row['variance']) }}</td>
                    <td class="right-text {{ $varianceClass($row['variance_percent_sales']) }}">{{ $formatPercent($row['variance_percent_sales']) }}</td>
                </tr>
            @endforeach

            <tr class="subtotal">
                <td class="left-text">Total Pendapatan</td>
                <td class="right-text">{{ $formatAmount($summary['total_sales_current_year']) }}</td>
                <td class="right-text">{{ $formatPercent(100) }}</td>
                <td class="right-text">{{ $formatAmount($summary['total_sales_previous_year']) }}</td>
                <td class="right-text">{{ $formatPercent(100) }}</td>
                <td class="right-text {{ $varianceClass($summary['total_sales_variance']) }}">{{ $formatAmount($summary['total_sales_variance']) }}</td>
                <td class="right-text">{{ $formatPercent(0) }}</td>
            </tr>

            <tr class="section"><td colspan="7">Harga Pokok Penjualan</td></tr>
            @foreach($cogsRows as $row)
                <tr class="detail">
                    <td class="left-text">{{ $rowLabel($row) }}</td>
                    <td class="right-text {{ (float) $row['current_year'] < 0 ? 'negative' : '' }}">{{ $formatAmount($row['current_year']) }}</td>
                    <td class="right-text {{ (float) $row['current_year_percent_sales'] < 0 ? 'negative' : '' }}">{{ $formatPercent($row['current_year_percent_sales']) }}</td>
                    <td class="right-text {{ (float) $row['previous_year'] < 0 ? 'negative' : '' }}">{{ $formatAmount($row['previous_year']) }}</td>
                    <td class="right-text {{ (float) $row['previous_year_percent_sales'] < 0 ? 'negative' : '' }}">{{ $formatPercent($row['previous_year_percent_sales']) }}</td>
                    <td class="right-text {{ $varianceClass($row['variance']) }}">{{ $formatAmount($row['variance']) }}</td>
                    <td class="right-text {{ $varianceClass($row['variance_percent_sales']) }}">{{ $formatPercent($row['variance_percent_sales']) }}</td>
                </tr>
            @endforeach

            <tr class="subtotal">
                <td class="left-text">Total HPP</td>
                <td class="right-text {{ (float) $summary['total_cogs_current_year'] < 0 ? 'negative' : '' }}">{{ $formatAmount($summary['total_cogs_current_year']) }}</td>
                <td class="right-text {{ $safePercent($summary['total_cogs_current_year'], $summary['total_sales_current_year']) < 0 ? 'negative' : '' }}">{{ $formatPercent($safePercent($summary['total_cogs_current_year'], $summary['total_sales_current_year'])) }}</td>
                <td class="right-text {{ (float) $summary['total_cogs_previous_year'] < 0 ? 'negative' : '' }}">{{ $formatAmount($summary['total_cogs_previous_year']) }}</td>
                <td class="right-text {{ $safePercent($summary['total_cogs_previous_year'], $summary['total_sales_previous_year']) < 0 ? 'negative' : '' }}">{{ $formatPercent($safePercent($summary['total_cogs_previous_year'], $summary['total_sales_previous_year'])) }}</td>
                <td class="right-text {{ $varianceClass($summary['total_cogs_variance']) }}">{{ $formatAmount($summary['total_cogs_variance']) }}</td>
                <td class="right-text {{ $varianceClass($safePercent($summary['total_cogs_variance'], $summary['total_sales_variance'])) }}">{{ $formatPercent($safePercent($summary['total_cogs_variance'], $summary['total_sales_variance'])) }}</td>
            </tr>

            <tr class="gross-profit">
                <td class="left-text">Gross Profit</td>
                <td class="right-text {{ (float) $summary['gross_profit_current_year'] < 0 ? 'negative' : '' }}">{{ $formatAmount($summary['gross_profit_current_year']) }}</td>
                <td class="right-text">{{ $formatPercent($safePercent($summary['gross_profit_current_year'], $summary['total_sales_current_year'])) }}</td>
                <td class="right-text {{ (float) $summary['gross_profit_previous_year'] < 0 ? 'negative' : '' }}">{{ $formatAmount($summary['gross_profit_previous_year']) }}</td>
                <td class="right-text">{{ $formatPercent($safePercent($summary['gross_profit_previous_year'], $summary['total_sales_previous_year'])) }}</td>
                <td class="right-text {{ $varianceClass($summary['gross_profit_variance']) }}">{{ $formatAmount($summary['gross_profit_variance']) }}</td>
                <td class="right-text {{ $varianceClass($safePercent($summary['gross_profit_variance'], $summary['total_sales_variance'])) }}">{{ $formatPercent($safePercent($summary['gross_profit_variance'], $summary['total_sales_variance'])) }}</td>
            </tr>

            <tr class="section"><td colspan="7">Biaya Operasional</td></tr>
            @foreach($operationalRows as $row)
                <tr class="detail">
                    <td class="left-text">{{ $rowLabel($row) }}</td>
                    <td class="right-text {{ (float) $row['current_year'] < 0 ? 'negative' : '' }}">{{ $formatAmount($row['current_year']) }}</td>
                    <td class="right-text {{ (float) $row['current_year_percent_sales'] < 0 ? 'negative' : '' }}">{{ $formatPercent($row['current_year_percent_sales']) }}</td>
                    <td class="right-text {{ (float) $row['previous_year'] < 0 ? 'negative' : '' }}">{{ $formatAmount($row['previous_year']) }}</td>
                    <td class="right-text {{ (float) $row['previous_year_percent_sales'] < 0 ? 'negative' : '' }}">{{ $formatPercent($row['previous_year_percent_sales']) }}</td>
                    <td class="right-text {{ $varianceClass($row['variance']) }}">{{ $formatAmount($row['variance']) }}</td>
                    <td class="right-text {{ $varianceClass($row['variance_percent_sales']) }}">{{ $formatPercent($row['variance_percent_sales']) }}</td>
                </tr>
            @endforeach

            <tr class="subtotal">
                <td class="left-text">Total Biaya Operasional</td>
                <td class="right-text {{ (float) $summary['total_operating_expense_current_year'] < 0 ? 'negative' : '' }}">{{ $formatAmount($summary['total_operating_expense_current_year']) }}</td>
                <td class="right-text">{{ $formatPercent($safePercent($summary['total_operating_expense_current_year'], $summary['total_sales_current_year'])) }}</td>
                <td class="right-text {{ (float) $summary['total_operating_expense_previous_year'] < 0 ? 'negative' : '' }}">{{ $formatAmount($summary['total_operating_expense_previous_year']) }}</td>
                <td class="right-text">{{ $formatPercent($safePercent($summary['total_operating_expense_previous_year'], $summary['total_sales_previous_year'])) }}</td>
                <td class="right-text {{ $varianceClass($summary['total_operating_expense_variance']) }}">{{ $formatAmount($summary['total_operating_expense_variance']) }}</td>
                <td class="right-text {{ $varianceClass($safePercent($summary['total_operating_expense_variance'], $summary['total_sales_variance'])) }}">{{ $formatPercent($safePercent($summary['total_operating_expense_variance'], $summary['total_sales_variance'])) }}</td>
            </tr>

            <tr class="section"><td colspan="7">Lain-lain</td></tr>
            @foreach($otherRows as $row)
                <tr class="detail">
                    <td class="left-text">{{ $rowLabel($row) }}</td>
                    <td class="right-text {{ (float) $row['current_year'] < 0 ? 'negative' : '' }}">{{ $formatAmount($row['current_year']) }}</td>
                    <td class="right-text {{ (float) $row['current_year_percent_sales'] < 0 ? 'negative' : '' }}">{{ $formatPercent($row['current_year_percent_sales']) }}</td>
                    <td class="right-text {{ (float) $row['previous_year'] < 0 ? 'negative' : '' }}">{{ $formatAmount($row['previous_year']) }}</td>
                    <td class="right-text {{ (float) $row['previous_year_percent_sales'] < 0 ? 'negative' : '' }}">{{ $formatPercent($row['previous_year_percent_sales']) }}</td>
                    <td class="right-text {{ $varianceClass($row['variance']) }}">{{ $formatAmount($row['variance']) }}</td>
                    <td class="right-text {{ $varianceClass($row['variance_percent_sales']) }}">{{ $formatPercent($row['variance_percent_sales']) }}</td>
                </tr>
            @endforeach

            @foreach($subtotalRows as $item)
                @php
                    $current = (float) ($summary[$item['key'].'_current_year'] ?? 0);
                    $previous = (float) ($summary[$item['key'].'_previous_year'] ?? 0);
                    $variance = (float) ($summary[$item['key'].'_variance'] ?? 0);
                    $currentPct = $safePercent($current, $summary['total_sales_current_year']);
                    $previousPct = $safePercent($previous, $summary['total_sales_previous_year']);
                    $variancePct = $safePercent($variance, $summary['total_sales_variance']);
                @endphp
                <tr class="{{ $item['class'] }}">
                    <td class="left-text">{{ $item['label'] }}</td>
                    <td class="right-text {{ $current < 0 ? 'negative' : '' }}">{{ $formatAmount($current) }}</td>
                    <td class="right-text {{ $currentPct < 0 ? 'negative' : '' }}">{{ $formatPercent($currentPct) }}</td>
                    <td class="right-text {{ $previous < 0 ? 'negative' : '' }}">{{ $formatAmount($previous) }}</td>
                    <td class="right-text {{ $previousPct < 0 ? 'negative' : '' }}">{{ $formatPercent($previousPct) }}</td>
                    <td class="right-text {{ $varianceClass($variance) }}">{{ $formatAmount($variance) }}</td>
                    <td class="right-text {{ $varianceClass($variancePct) }}">{{ $formatPercent($variancePct) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <div>Prepared for management reporting purpose.</div>
        <div>Page size: A4 Portrait</div>
    </div>
</div>

<script>
    window.addEventListener('load', () => window.print());
</script>
</body>
</html>
