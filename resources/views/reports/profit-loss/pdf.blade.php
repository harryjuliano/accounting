<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Rugi Laba</title>
    <style>
        @page { margin: 18px 20px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; }
        .header { background: #3f67b0; color: #fff; padding: 10px 12px; margin-bottom: 10px; }
        .header h1 { margin: 0; font-size: 14px; }
        .header p { margin: 2px 0 0; font-size: 11px; }
        .meta { margin-bottom: 8px; font-size: 10px; color: #374151; }
        .toolbar { margin-bottom: 10px; text-align: right; }
        .toolbar button { border: 1px solid #1d4ed8; background: #2563eb; color: #fff; padding: 6px 10px; border-radius: 4px; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #d1d5db; padding: 4px 5px; vertical-align: top; }
        th { background: #e5e7eb; font-size: 10px; text-transform: uppercase; }
        .left { text-align: left; }
        .right { text-align: right; }
        .section { background: #f3f4f6; color: #1d4ed8; font-weight: 700; }
        .negative { color: #b91c1c; }
        .summary { background: #dbeafe; font-weight: 700; color: #1d4ed8; }
        @media print {
            .toolbar { display: none; }
        }
    </style>
</head>
<body>
<div class="toolbar">
    <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
</div>
<div class="header">
    <h1>Laporan Laba Rugi</h1>
    <p>Periode {{ $filters['period'] }}/{{ $filters['year'] }} ({{ $filters['type'] }})</p>
</div>
<div class="meta">
    Dicetak: {{ $generatedAt }} | Status: {{ strtoupper($filters['status']) }}
</div>

@php
    $formatAmount = fn ($value) => number_format((float) $value, 2, ',', '.');
    $previousYearLabel = (int) $filters['year'] - 1;

    $getLabel = function ($row, $drillLevel) {
        if ($drillLevel >= 4) {
            return $row['coa_level_4'] ?? $row['coa_level_3'] ?? $row['coa_level_2'] ?? $row['coa_level_1'] ?? '-';
        }

        if ($drillLevel === 3) {
            return $row['coa_level_3'] ?? $row['coa_level_2'] ?? $row['coa_level_1'] ?? '-';
        }

        if ($drillLevel === 2) {
            return $row['coa_level_2'] ?? $row['coa_level_1'] ?? '-';
        }

        return $row['coa_level_1'] ?? '-';
    };

    $isSection = fn ($label) => str_contains(strtolower($label), 'pendapatan') || str_contains(strtolower($label), 'beban') || str_contains(strtolower($label), 'biaya');
@endphp

<table>
    <thead>
    <tr>
        <th class="left">COA</th>
        <th class="right">{{ $filters['year'] }}</th>
        <th class="right">{{ $previousYearLabel }}</th>
        <th class="right">Variance</th>
    </tr>
    </thead>
    <tbody>
    @forelse($rows as $row)
        @php
            $label = $getLabel($row, (int) $filters['drill_level']);
            $rowClass = $isSection($label) ? 'section' : '';
            $currentNegative = (float) $row['current_year'] < 0 ? 'negative' : '';
            $previousNegative = (float) $row['previous_year'] < 0 ? 'negative' : '';
            $varianceNegative = (float) $row['variance'] < 0 ? 'negative' : '';
        @endphp
        <tr class="{{ $rowClass }}">
            <td class="left">{{ $label }}</td>
            <td class="right {{ $currentNegative }}">{{ $formatAmount($row['current_year']) }}</td>
            <td class="right {{ $previousNegative }}">{{ $formatAmount($row['previous_year']) }}</td>
            <td class="right {{ $varianceNegative }}">{{ $formatAmount($row['variance']) }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="4" class="left">Data Laporan Rugi Laba tidak ditemukan.</td>
        </tr>
    @endforelse

    @if(count($rows) > 0)
        <tr class="summary">
            <td class="left">Laba Bersih</td>
            <td class="right {{ (float) $summary['net_profit_current_year'] < 0 ? 'negative' : '' }}">{{ $formatAmount($summary['net_profit_current_year']) }}</td>
            <td class="right {{ (float) $summary['net_profit_previous_year'] < 0 ? 'negative' : '' }}">{{ $formatAmount($summary['net_profit_previous_year']) }}</td>
            <td class="right {{ (float) $summary['net_profit_variance'] < 0 ? 'negative' : '' }}">{{ $formatAmount($summary['net_profit_variance']) }}</td>
        </tr>
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
