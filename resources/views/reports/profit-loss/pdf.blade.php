<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Laba Rugi</title>
    <style>
        @page { size: A4 portrait; margin: 10mm 12mm; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111827; margin: 0; }
        .toolbar { margin-bottom: 8px; text-align: right; }
        .toolbar button { border: 1px solid #1d4ed8; background: #2563eb; color: #fff; padding: 4px 8px; border-radius: 3px; font-size: 9px; }

        .report-header { background: #3f67b0; color: #fff; padding: 8px 10px; }
        .report-header .company-name { font-size: 12px; font-weight: 700; margin: 0; }
        .report-header .company-meta { margin-top: 2px; font-size: 8.4px; line-height: 1.35; }
        .report-header .title { margin-top: 6px; font-size: 11px; font-weight: 700; }
        .report-header .subtitle { margin-top: 1px; font-size: 8.8px; }

        .meta-row { margin: 6px 0 6px; display: table; width: 100%; font-size: 8.5px; color: #374151; }
        .meta-left, .meta-right { display: table-cell; width: 50%; vertical-align: top; }
        .meta-right { text-align: right; }

        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { padding: 2px 4px; vertical-align: middle; border-bottom: 0.7px solid #d1d5db; }
        thead th { background: #e5e7eb; font-size: 8.1px; text-transform: uppercase; letter-spacing: .2px; }
        .left { text-align: left; }
        .right { text-align: right; }
        .indent-0 { padding-left: 3px; }
        .indent-1 { padding-left: 11px; }
        .indent-2 { padding-left: 17px; }
        .negative { color: #b91c1c; }

        .section td { background: #f7f9fc; color: #1e40af; font-weight: 700; border-bottom: 0.9px solid #9ca3af; }
        .subtotal td { background: #e8eefc; color: #1e3a8a; font-weight: 700; border-top: 0.9px solid #94a3b8; border-bottom: 0.9px solid #94a3b8; }
        .grand-total td { background: #dbeafe; color: #1e3a8a; font-weight: 700; border-top: 1.2px solid #64748b; border-bottom: 1.2px solid #64748b; }

        @media print {
            .toolbar { display: none; }
        }
    </style>
</head>
<body>
@php
    $formatAmount = function ($value) {
        $number = number_format(abs((float) $value), 0, ',', '.');
        return (float) $value < 0 ? '(' . $number . ')' : $number;
    };
    $previousYearLabel = (int) $filters['year'] - 1;
    $periodDate = \Carbon\Carbon::create((int) $filters['year'], (int) $filters['period'], 1)->endOfMonth();
    $periodLabel = $periodDate->translatedFormat('F d, Y');

    $subtotals = [
        ['label' => 'Total Pendapatan', 'key' => 'total_sales'],
        ['label' => 'Total HPP', 'key' => 'total_cogs'],
        ['label' => 'Gross Profit', 'key' => 'gross_profit'],
        ['label' => 'Total Biaya Operasi', 'key' => 'total_operating_expense'],
        ['label' => 'Net Profit Before Tax', 'key' => 'net_profit_before_tax'],
        ['label' => 'Pajak Penghasilan', 'key' => 'income_tax'],
        ['label' => 'Net Profit After Tax', 'key' => 'net_profit_after_tax'],
    ];
@endphp

<div class="toolbar">
    <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
</div>

<div class="report-header">
    <p class="company-name">{{ $companyProfile['legal_name'] ?: $companyProfile['name'] }}</p>
    <div class="company-meta">
        @if(!empty($companyProfile['tax_id'])) NPWP: {{ $companyProfile['tax_id'] }} | @endif
        Mata Uang: {{ $companyProfile['base_currency_code'] ?: 'IDR' }}
        @if(!empty($companyProfile['branch_name']))
            | Cabang: {{ $companyProfile['branch_name'] }} ({{ $companyProfile['branch_code'] }})
        @endif
        @if(!empty($companyProfile['branch_city']))
            | {{ $companyProfile['branch_city'] }}
        @endif
    </div>
    <div class="title">Laporan Laba Rugi</div>
    <div class="subtitle">{{ $periodLabel }} | Perbandingan {{ $filters['year'] }} vs {{ $previousYearLabel }} | {{ $filters['type'] }}</div>
</div>

<div class="meta-row">
    <div class="meta-left">Status Jurnal: {{ strtoupper($filters['status']) }}</div>
    <div class="meta-right">Dicetak: {{ $generatedAt }}</div>
</div>

<table>
    <thead>
    <tr>
        <th class="left" style="width: 44%;">Uraian (COA Level 3)</th>
        <th class="right" style="width: 18%;">{{ $filters['year'] }}</th>
        <th class="right" style="width: 10%;">%</th>
        <th class="right" style="width: 18%;">{{ $previousYearLabel }}</th>
        <th class="right" style="width: 10%;">%</th>
    </tr>
    </thead>
    <tbody>
    @forelse($rows as $row)
        @php
            $label = $row['coa_level_3'] ?? $row['coa_level_2'] ?? $row['coa_level_1'] ?? '-';
            $lowerLabel = strtolower($label);
            $isSection = str_contains($lowerLabel, 'pendapatan') || str_contains($lowerLabel, 'beban') || str_contains($lowerLabel, 'biaya') || str_contains($lowerLabel, 'hpp');
            $currentPercent = number_format((float) ($row['current_year_percent_sales'] ?? 0), 2, '.', ',') . '%';
            $previousPercent = number_format((float) ($row['previous_year_percent_sales'] ?? 0), 2, '.', ',') . '%';
        @endphp
        <tr class="{{ $isSection ? 'section' : '' }}">
            <td class="left indent-1">{{ $label }}</td>
            <td class="right {{ (float) $row['current_year'] < 0 ? 'negative' : '' }}">{{ $formatAmount($row['current_year']) }}</td>
            <td class="right {{ (float) ($row['current_year_percent_sales'] ?? 0) < 0 ? 'negative' : '' }}">{{ $currentPercent }}</td>
            <td class="right {{ (float) $row['previous_year'] < 0 ? 'negative' : '' }}">{{ $formatAmount($row['previous_year']) }}</td>
            <td class="right {{ (float) ($row['previous_year_percent_sales'] ?? 0) < 0 ? 'negative' : '' }}">{{ $previousPercent }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="5" class="left">Data Laporan Laba Rugi tidak ditemukan.</td>
        </tr>
    @endforelse

    @if(count($rows) > 0)
        @foreach($subtotals as $subtotal)
            @php
                $currentValue = (float) ($summary[$subtotal['key'].'_current_year'] ?? 0);
                $previousValue = (float) ($summary[$subtotal['key'].'_previous_year'] ?? 0);
                $currentPct = abs((float) $summary['total_sales_current_year']) > 0.000001
                    ? ($currentValue / (float) $summary['total_sales_current_year']) * 100
                    : 0;
                $previousPct = abs((float) $summary['total_sales_previous_year']) > 0.000001
                    ? ($previousValue / (float) $summary['total_sales_previous_year']) * 100
                    : 0;
            @endphp
            <tr class="{{ $subtotal['key'] === 'net_profit_after_tax' ? 'grand-total' : 'subtotal' }}">
                <td class="left indent-0">{{ $subtotal['label'] }}</td>
                <td class="right {{ $currentValue < 0 ? 'negative' : '' }}">{{ $formatAmount($currentValue) }}</td>
                <td class="right {{ $currentPct < 0 ? 'negative' : '' }}">{{ number_format($currentPct, 2, '.', ',') }}%</td>
                <td class="right {{ $previousValue < 0 ? 'negative' : '' }}">{{ $formatAmount($previousValue) }}</td>
                <td class="right {{ $previousPct < 0 ? 'negative' : '' }}">{{ number_format($previousPct, 2, '.', ',') }}%</td>
            </tr>
        @endforeach
    @endif
    </tbody>
</table>

<script>
    window.addEventListener('load', () => {
        window.print();
    });
</script>
</body>
</html>
