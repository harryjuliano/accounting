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
    $safePercent = fn ($numerator, $denominator) => abs((float) $denominator) > 0.000001
        ? (((float) $numerator / (float) $denominator) * 100)
        : 0;

    $isHppByName = function (string $label): bool {
        $name = strtolower($label);
        return str_contains($name, 'cost of good sold')
            || str_contains($name, 'factory overhead')
            || str_contains($name, 'cost of services');
    };
    $isOtherByName = function (string $label): bool {
        $name = strtolower($label);
        return str_contains($name, 'other income')
            || str_contains($name, 'other expense')
            || str_contains($name, 'pendapatan lain')
            || str_contains($name, 'biaya lain');
    };
    $isTaxByName = fn (string $label): bool => str_contains(strtolower($label), 'corporate tax')
        || str_contains(strtolower($label), 'pajak penghasilan');

    $rowLabel = fn ($row) => $row['coa_level_3'] ?? $row['coa_level_2'] ?? $row['coa_level_1'] ?? '-';
    $revenueRows = collect();
    $hppRows = collect();
    $operationalRows = collect();
    $otherIncomeRows = collect();
    $otherExpenseRows = collect();
    $taxRows = collect();

    foreach (collect($rows) as $row) {
        $label = $rowLabel($row);
        $group = $row['account_group_type'] ?? '';

        if ($group === 'revenue') {
            $revenueRows->push($row);
            continue;
        }

        if ($group === 'cogs' || $isHppByName($label)) {
            $hppRows->push($row);
            continue;
        }

        if ($isTaxByName($label)) {
            $taxRows->push($row);
            continue;
        }

        if ($group === 'other_income' || ($isOtherByName($label) && str_contains(strtolower($label), 'income'))) {
            $otherIncomeRows->push($row);
            continue;
        }

        if ($group === 'other_expense' || $isOtherByName($label)) {
            $otherExpenseRows->push($row);
            continue;
        }

        $operationalRows->push($row);
    }

    $sumCurrentMonth = fn ($items) => (float) $items->sum('current_month');
    $sumYearToDate = fn ($items) => (float) $items->sum('year_to_date');
    $sumLastYearToDate = fn ($items) => (float) $items->sum('last_year_to_date');

    $totalRevenueCurrentMonth = $sumCurrentMonth($revenueRows);
    $totalRevenueYearToDate = $sumYearToDate($revenueRows);
    $totalRevenueLastYearToDate = $sumLastYearToDate($revenueRows);
    $totalHppCurrentMonth = $sumCurrentMonth($hppRows);
    $totalHppYearToDate = $sumYearToDate($hppRows);
    $totalHppLastYearToDate = $sumLastYearToDate($hppRows);
    $grossProfitCurrentMonth = $totalRevenueCurrentMonth - $totalHppCurrentMonth;
    $grossProfitYearToDate = $totalRevenueYearToDate - $totalHppYearToDate;
    $grossProfitLastYearToDate = $totalRevenueLastYearToDate - $totalHppLastYearToDate;

    $totalOperationalCurrentMonth = $sumCurrentMonth($operationalRows);
    $totalOperationalYearToDate = $sumYearToDate($operationalRows);
    $totalOperationalLastYearToDate = $sumLastYearToDate($operationalRows);

    $totalOtherIncomeCurrentMonth = $sumCurrentMonth($otherIncomeRows);
    $totalOtherIncomeYearToDate = $sumYearToDate($otherIncomeRows);
    $totalOtherIncomeLastYearToDate = $sumLastYearToDate($otherIncomeRows);
    $totalOtherExpenseCurrentMonth = $sumCurrentMonth($otherExpenseRows);
    $totalOtherExpenseYearToDate = $sumYearToDate($otherExpenseRows);
    $totalOtherExpenseLastYearToDate = $sumLastYearToDate($otherExpenseRows);

    $netBeforeTaxCurrentMonth = $grossProfitCurrentMonth - $totalOperationalCurrentMonth - $totalOtherIncomeCurrentMonth - $totalOtherExpenseCurrentMonth;
    $netBeforeTaxYearToDate = $grossProfitYearToDate - $totalOperationalYearToDate - $totalOtherIncomeYearToDate - $totalOtherExpenseYearToDate;
    $netBeforeTaxLastYearToDate = $grossProfitLastYearToDate - $totalOperationalLastYearToDate - $totalOtherIncomeLastYearToDate - $totalOtherExpenseLastYearToDate;

    $taxCurrentMonth = $sumCurrentMonth($taxRows);
    $taxYearToDate = $sumYearToDate($taxRows);
    $taxLastYearToDate = $sumLastYearToDate($taxRows);

    $netAfterTaxCurrentMonth = $netBeforeTaxCurrentMonth - $taxCurrentMonth;
    $netAfterTaxYearToDate = $netBeforeTaxYearToDate - $taxYearToDate;
    $netAfterTaxLastYearToDate = $netBeforeTaxLastYearToDate - $taxLastYearToDate;
@endphp

<div class="page">
    <div class="toolbar">
        <button onclick="window.print()">Print</button>
    </div>

    <div class="report-header">
        <p class="company-name">{{ $companyProfile['legal_name'] ?: $companyProfile['name'] }}</p>
        <h1 class="report-title">Laporan Laba Rugi</h1>
        <div class="report-subtitle">Periode: {{ ucfirst($periodLabel) }} | YTD Jan-{{ $periodDate->translatedFormat('F') }} {{ $currentYear }} vs {{ $previousYear }}</div>
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
                <th rowspan="2" class="left-text">Uraian</th>
                <th colspan="2" class="right-text">Current Month ({{ $periodDate->translatedFormat('M') }} {{ $currentYear }})</th>
                <th colspan="2" class="right-text">Year to Date (Jan-{{ $periodDate->translatedFormat('M') }} {{ $currentYear }})</th>
                <th colspan="2" class="right-text">Last Year to Date (Jan-{{ $periodDate->translatedFormat('M') }} {{ $previousYear }})</th>
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
                    <td class="right-text {{ (float) $row['current_month'] < 0 ? 'negative' : '' }}">{{ $formatAmount($row['current_month']) }}</td>
                    <td class="right-text {{ (float) $row['current_month_percent_sales'] < 0 ? 'negative' : '' }}">{{ $formatPercent($row['current_month_percent_sales']) }}</td>
                    <td class="right-text {{ (float) $row['year_to_date'] < 0 ? 'negative' : '' }}">{{ $formatAmount($row['year_to_date']) }}</td>
                    <td class="right-text {{ (float) $row['year_to_date_percent_sales'] < 0 ? 'negative' : '' }}">{{ $formatPercent($row['year_to_date_percent_sales']) }}</td>
                    <td class="right-text {{ (float) $row['last_year_to_date'] < 0 ? 'negative' : '' }}">{{ $formatAmount($row['last_year_to_date']) }}</td>
                    <td class="right-text {{ (float) $row['last_year_to_date_percent_sales'] < 0 ? 'negative' : '' }}">{{ $formatPercent($row['last_year_to_date_percent_sales']) }}</td>
                </tr>
            @endforeach

            <tr class="subtotal">
                <td class="left-text">Total Pendapatan</td>
                <td class="right-text">{{ $formatAmount($totalRevenueCurrentMonth) }}</td>
                <td class="right-text">{{ $formatPercent(100) }}</td>
                <td class="right-text">{{ $formatAmount($totalRevenueYearToDate) }}</td>
                <td class="right-text">{{ $formatPercent(100) }}</td>
                <td class="right-text {{ (float) $totalRevenueLastYearToDate < 0 ? 'negative' : '' }}">{{ $formatAmount($totalRevenueLastYearToDate) }}</td>
                <td class="right-text">{{ $formatPercent(0) }}</td>
            </tr>

            <tr class="section"><td colspan="7">Harga Pokok Penjualan</td></tr>
            @foreach($hppRows as $row)
                <tr class="detail">
                    <td class="left-text">{{ $rowLabel($row) }}</td>
                    <td class="right-text {{ (float) $row['current_month'] < 0 ? 'negative' : '' }}">{{ $formatAmount($row['current_month']) }}</td>
                    <td class="right-text {{ (float) $row['current_month_percent_sales'] < 0 ? 'negative' : '' }}">{{ $formatPercent($row['current_month_percent_sales']) }}</td>
                    <td class="right-text {{ (float) $row['year_to_date'] < 0 ? 'negative' : '' }}">{{ $formatAmount($row['year_to_date']) }}</td>
                    <td class="right-text {{ (float) $row['year_to_date_percent_sales'] < 0 ? 'negative' : '' }}">{{ $formatPercent($row['year_to_date_percent_sales']) }}</td>
                    <td class="right-text {{ (float) $row['last_year_to_date'] < 0 ? 'negative' : '' }}">{{ $formatAmount($row['last_year_to_date']) }}</td>
                    <td class="right-text {{ (float) $row['last_year_to_date_percent_sales'] < 0 ? 'negative' : '' }}">{{ $formatPercent($row['last_year_to_date_percent_sales']) }}</td>
                </tr>
            @endforeach

            <tr class="subtotal">
                <td class="left-text">Total HPP</td>
                <td class="right-text {{ (float) $totalHppCurrentMonth < 0 ? 'negative' : '' }}">{{ $formatAmount($totalHppCurrentMonth) }}</td>
                <td class="right-text {{ $safePercent($totalHppCurrentMonth, $totalRevenueCurrentMonth) < 0 ? 'negative' : '' }}">{{ $formatPercent($safePercent($totalHppCurrentMonth, $totalRevenueCurrentMonth)) }}</td>
                <td class="right-text {{ (float) $totalHppYearToDate < 0 ? 'negative' : '' }}">{{ $formatAmount($totalHppYearToDate) }}</td>
                <td class="right-text {{ $safePercent($totalHppYearToDate, $totalRevenueYearToDate) < 0 ? 'negative' : '' }}">{{ $formatPercent($safePercent($totalHppYearToDate, $totalRevenueYearToDate)) }}</td>
                <td class="right-text {{ (float) $totalHppLastYearToDate < 0 ? 'negative' : '' }}">{{ $formatAmount($totalHppLastYearToDate) }}</td>
                <td class="right-text {{ $safePercent($totalHppLastYearToDate, $totalRevenueLastYearToDate) < 0 ? 'negative' : '' }}">{{ $formatPercent($safePercent($totalHppLastYearToDate, $totalRevenueLastYearToDate)) }}</td>
            </tr>

            <tr class="gross-profit">
                <td class="left-text">Gross Profit</td>
                <td class="right-text {{ (float) $grossProfitCurrentMonth < 0 ? 'negative' : '' }}">{{ $formatAmount($grossProfitCurrentMonth) }}</td>
                <td class="right-text">{{ $formatPercent($safePercent($grossProfitCurrentMonth, $totalRevenueCurrentMonth)) }}</td>
                <td class="right-text {{ (float) $grossProfitYearToDate < 0 ? 'negative' : '' }}">{{ $formatAmount($grossProfitYearToDate) }}</td>
                <td class="right-text">{{ $formatPercent($safePercent($grossProfitYearToDate, $totalRevenueYearToDate)) }}</td>
                <td class="right-text {{ (float) $grossProfitLastYearToDate < 0 ? 'negative' : '' }}">{{ $formatAmount($grossProfitLastYearToDate) }}</td>
                <td class="right-text {{ $safePercent($grossProfitLastYearToDate, $totalRevenueLastYearToDate) < 0 ? 'negative' : '' }}">{{ $formatPercent($safePercent($grossProfitLastYearToDate, $totalRevenueLastYearToDate)) }}</td>
            </tr>

            <tr class="section"><td colspan="7">Biaya Operasional</td></tr>
            @foreach($operationalRows as $row)
                <tr class="detail">
                    <td class="left-text">{{ $rowLabel($row) }}</td>
                    <td class="right-text {{ (float) $row['current_month'] < 0 ? 'negative' : '' }}">{{ $formatAmount($row['current_month']) }}</td>
                    <td class="right-text {{ (float) $row['current_month_percent_sales'] < 0 ? 'negative' : '' }}">{{ $formatPercent($row['current_month_percent_sales']) }}</td>
                    <td class="right-text {{ (float) $row['year_to_date'] < 0 ? 'negative' : '' }}">{{ $formatAmount($row['year_to_date']) }}</td>
                    <td class="right-text {{ (float) $row['year_to_date_percent_sales'] < 0 ? 'negative' : '' }}">{{ $formatPercent($row['year_to_date_percent_sales']) }}</td>
                    <td class="right-text {{ (float) $row['last_year_to_date'] < 0 ? 'negative' : '' }}">{{ $formatAmount($row['last_year_to_date']) }}</td>
                    <td class="right-text {{ (float) $row['last_year_to_date_percent_sales'] < 0 ? 'negative' : '' }}">{{ $formatPercent($row['last_year_to_date_percent_sales']) }}</td>
                </tr>
            @endforeach

            <tr class="subtotal">
                <td class="left-text">Total Biaya Operasional</td>
                <td class="right-text {{ (float) $totalOperationalCurrentMonth < 0 ? 'negative' : '' }}">{{ $formatAmount($totalOperationalCurrentMonth) }}</td>
                <td class="right-text">{{ $formatPercent($safePercent($totalOperationalCurrentMonth, $totalRevenueCurrentMonth)) }}</td>
                <td class="right-text {{ (float) $totalOperationalYearToDate < 0 ? 'negative' : '' }}">{{ $formatAmount($totalOperationalYearToDate) }}</td>
                <td class="right-text">{{ $formatPercent($safePercent($totalOperationalYearToDate, $totalRevenueYearToDate)) }}</td>
                <td class="right-text {{ (float) $totalOperationalLastYearToDate < 0 ? 'negative' : '' }}">{{ $formatAmount($totalOperationalLastYearToDate) }}</td>
                <td class="right-text {{ $safePercent($totalOperationalLastYearToDate, $totalRevenueLastYearToDate) < 0 ? 'negative' : '' }}">{{ $formatPercent($safePercent($totalOperationalLastYearToDate, $totalRevenueLastYearToDate)) }}</td>
            </tr>

            <tr class="section"><td colspan="7">Lain-lain</td></tr>
            @foreach($otherIncomeRows->concat($otherExpenseRows) as $row)
                <tr class="detail">
                    <td class="left-text">{{ $rowLabel($row) }}</td>
                    <td class="right-text {{ (float) $row['current_month'] < 0 ? 'negative' : '' }}">{{ $formatAmount($row['current_month']) }}</td>
                    <td class="right-text {{ (float) $row['current_month_percent_sales'] < 0 ? 'negative' : '' }}">{{ $formatPercent($row['current_month_percent_sales']) }}</td>
                    <td class="right-text {{ (float) $row['year_to_date'] < 0 ? 'negative' : '' }}">{{ $formatAmount($row['year_to_date']) }}</td>
                    <td class="right-text {{ (float) $row['year_to_date_percent_sales'] < 0 ? 'negative' : '' }}">{{ $formatPercent($row['year_to_date_percent_sales']) }}</td>
                    <td class="right-text {{ (float) $row['last_year_to_date'] < 0 ? 'negative' : '' }}">{{ $formatAmount($row['last_year_to_date']) }}</td>
                    <td class="right-text {{ (float) $row['last_year_to_date_percent_sales'] < 0 ? 'negative' : '' }}">{{ $formatPercent($row['last_year_to_date_percent_sales']) }}</td>
                </tr>
            @endforeach

            <tr class="subtotal">
                <td class="left-text">Net Profit Before Tax</td>
                <td class="right-text {{ $netBeforeTaxCurrentMonth < 0 ? 'negative' : '' }}">{{ $formatAmount($netBeforeTaxCurrentMonth) }}</td>
                <td class="right-text {{ $safePercent($netBeforeTaxCurrentMonth, $totalRevenueCurrentMonth) < 0 ? 'negative' : '' }}">{{ $formatPercent($safePercent($netBeforeTaxCurrentMonth, $totalRevenueCurrentMonth)) }}</td>
                <td class="right-text {{ $netBeforeTaxYearToDate < 0 ? 'negative' : '' }}">{{ $formatAmount($netBeforeTaxYearToDate) }}</td>
                <td class="right-text {{ $safePercent($netBeforeTaxYearToDate, $totalRevenueYearToDate) < 0 ? 'negative' : '' }}">{{ $formatPercent($safePercent($netBeforeTaxYearToDate, $totalRevenueYearToDate)) }}</td>
                <td class="right-text {{ $netBeforeTaxLastYearToDate < 0 ? 'negative' : '' }}">{{ $formatAmount($netBeforeTaxLastYearToDate) }}</td>
                <td class="right-text {{ $safePercent($netBeforeTaxLastYearToDate, $totalRevenueLastYearToDate) < 0 ? 'negative' : '' }}">{{ $formatPercent($safePercent($netBeforeTaxLastYearToDate, $totalRevenueLastYearToDate)) }}</td>
            </tr>

            <tr class="detail">
                <td class="left-text">Pajak Penghasilan</td>
                <td class="right-text {{ $taxCurrentMonth < 0 ? 'negative' : '' }}">{{ $formatAmount($taxCurrentMonth) }}</td>
                <td class="right-text {{ $safePercent($taxCurrentMonth, $totalRevenueCurrentMonth) < 0 ? 'negative' : '' }}">{{ $formatPercent($safePercent($taxCurrentMonth, $totalRevenueCurrentMonth)) }}</td>
                <td class="right-text {{ $taxYearToDate < 0 ? 'negative' : '' }}">{{ $formatAmount($taxYearToDate) }}</td>
                <td class="right-text {{ $safePercent($taxYearToDate, $totalRevenueYearToDate) < 0 ? 'negative' : '' }}">{{ $formatPercent($safePercent($taxYearToDate, $totalRevenueYearToDate)) }}</td>
                <td class="right-text {{ $taxLastYearToDate < 0 ? 'negative' : '' }}">{{ $formatAmount($taxLastYearToDate) }}</td>
                <td class="right-text {{ $safePercent($taxLastYearToDate, $totalRevenueLastYearToDate) < 0 ? 'negative' : '' }}">{{ $formatPercent($safePercent($taxLastYearToDate, $totalRevenueLastYearToDate)) }}</td>
            </tr>

            <tr class="grand-total">
                <td class="left-text">Net Profit After Tax</td>
                <td class="right-text {{ $netAfterTaxCurrentMonth < 0 ? 'negative' : '' }}">{{ $formatAmount($netAfterTaxCurrentMonth) }}</td>
                <td class="right-text {{ $safePercent($netAfterTaxCurrentMonth, $totalRevenueCurrentMonth) < 0 ? 'negative' : '' }}">{{ $formatPercent($safePercent($netAfterTaxCurrentMonth, $totalRevenueCurrentMonth)) }}</td>
                <td class="right-text {{ $netAfterTaxYearToDate < 0 ? 'negative' : '' }}">{{ $formatAmount($netAfterTaxYearToDate) }}</td>
                <td class="right-text {{ $safePercent($netAfterTaxYearToDate, $totalRevenueYearToDate) < 0 ? 'negative' : '' }}">{{ $formatPercent($safePercent($netAfterTaxYearToDate, $totalRevenueYearToDate)) }}</td>
                <td class="right-text {{ $netAfterTaxLastYearToDate < 0 ? 'negative' : '' }}">{{ $formatAmount($netAfterTaxLastYearToDate) }}</td>
                <td class="right-text {{ $safePercent($netAfterTaxLastYearToDate, $totalRevenueLastYearToDate) < 0 ? 'negative' : '' }}">{{ $formatPercent($safePercent($netAfterTaxLastYearToDate, $totalRevenueLastYearToDate)) }}</td>
            </tr>
        </tbody>
    </table>
</div>

<script>
    window.addEventListener('load', () => window.print());
</script>
</body>
</html>
