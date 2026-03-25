<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Laporan Neraca</title>

<style>
    @page {
        size: A4 portrait;
        margin: 10mm 12mm;
    }

    :root{
        --text:#111827;
        --muted:#6b7280;
        --line:#d5dbe3;
        --line-soft:#e8edf3;
        --line-strong:#475569;
        --header:#0f172a;
        --section-bg:#f8fafc;
        --subsection-bg:#f2f6fb;
        --subtotal:#eef4ff;
        --grand:#e2ecff;
        --negative:#c62828;
        --positive:#0f766e;
    }

    * { box-sizing: border-box; }

    body {
        margin: 0;
        font-family: "Segoe UI", Arial, sans-serif;
        color: var(--text);
        font-size: 10px;
        background: #fff;
    }

    .page {
        width: 100%;
        margin: 0 auto;
    }

    .toolbar {
        display: flex;
        justify-content: flex-end;
        margin-bottom: 6px;
    }

    .toolbar button {
        border: 1px solid #334155;
        background: #fff;
        color: #0f172a;
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 10px;
        cursor: pointer;
    }

    .report-header {
        text-align: center;
        margin-bottom: 8px;
    }

    .company-name {
        font-size: 15px;
        font-weight: 700;
        margin: 0 0 2px 0;
        color: var(--header);
        letter-spacing: .2px;
    }

    .report-title {
        font-size: 18px;
        font-weight: 800;
        margin: 0;
        color: var(--header);
        text-transform: uppercase;
        letter-spacing: .35px;
    }

    .report-subtitle {
        margin-top: 4px;
        font-size: 10px;
        color: #374151;
    }

    .report-meta {
        margin-top: 8px;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        font-size: 9.5px;
        color: #374151;
        border-top: 1px solid var(--line-strong);
        border-bottom: 1px solid var(--line);
        padding: 5px 0;
    }

    .report-meta .left,
    .report-meta .right {
        width: 50%;
    }

    .report-meta .right {
        text-align: right;
    }

    table.report-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        margin-top: 8px;
    }

    .report-table col.desc    { width: 34%; }
    .report-table col.amt     { width: 12%; }
    .report-table col.pct     { width: 7%; }

    .report-table thead th {
        padding: 5px 5px;
        border-bottom: 1px solid var(--line-strong);
        color: #111827;
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: .25px;
        background: #fff;
    }

    .report-table thead tr.group-header th {
        font-size: 9px;
        font-weight: 700;
        color: #0f172a;
        border-top: 1.2px solid var(--line-strong);
        border-bottom: 1px solid var(--line);
        padding-top: 6px;
        padding-bottom: 6px;
    }

    .report-table thead tr.sub-header th {
        font-weight: 700;
        color: #475569;
        border-bottom: 1.2px solid var(--line-strong);
        padding-top: 4px;
        padding-bottom: 4px;
    }

    .report-table td {
        padding: 4px 5px;
        border-bottom: 0.7px solid var(--line-soft);
        vertical-align: middle;
        font-size: 10px;
        line-height: 1.15;
    }

    .left-text  { text-align: left; }
    .right-text { text-align: right; }

    .segment-header td {
        padding-top: 8px;
        padding-bottom: 5px;
        border-bottom: none;
        font-weight: 800;
        text-transform: uppercase;
        color: #0f172a;
        background: var(--section-bg);
        font-size: 10px;
    }

    .sub-header-row td {
        padding-top: 5px;
        padding-bottom: 5px;
        border-bottom: 1px solid var(--line);
        font-weight: 700;
        color: #1e3a8a;
        background: var(--subsection-bg);
        font-size: 9.5px;
    }

    .detail td:first-child {
        padding-left: 10px;
    }

    .subtotal td {
        font-weight: 700;
        background: var(--subtotal);
        border-top: 1px solid #94a3b8;
        border-bottom: 1px solid #94a3b8;
    }

    .total-segment td {
        font-weight: 800;
        background: #f8fbff;
        border-top: 1.2px solid #64748b;
        border-bottom: 1.2px solid #64748b;
    }

    .grand-total td {
        font-weight: 800;
        background: var(--grand);
        border-top: 1.8px solid #334155;
        border-bottom: 1.8px solid #334155;
        font-size: 10.5px;
    }

    .negative { color: var(--negative); }
    .positive { color: var(--positive); }

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
    $currentYear = (int) ($filters['year'] ?? now()->year);
    $previousYear = $currentYear - 1;
    $periodDate = \Carbon\Carbon::create($currentYear, (int) ($filters['period'] ?? 12), 1)->locale('id')->endOfMonth();
    $periodLabel = $periodDate->translatedFormat('F Y');
    $printedDate = \Carbon\Carbon::parse($generatedAt)->locale('id')->translatedFormat('d F Y');

    $rowsForPdf = collect($rows)->map(function (array $row) {
        $subgroupSource = $row['coa_level_2'] ?? $row['coa_level_1'] ?? 'General';
        $subgroupKey = \Illuminate\Support\Str::slug((string) $subgroupSource, '_');

        return [
            'segment_key' => $row['segment_key'] ?? 'other',
            'subgroup_key' => $subgroupKey !== '' ? $subgroupKey : 'general',
            'subgroup_label' => $subgroupSource,
            'coa_level_3' => $row['coa_level_3'] ?? $row['coa_level_2'] ?? $row['coa_level_1'] ?? '-',
            'current_year' => (float) ($row['current_year'] ?? 0),
            'previous_year' => (float) ($row['previous_year'] ?? 0),
            'current_year_percent_asset' => (float) ($row['current_year_percent_asset'] ?? 0),
            'previous_year_percent_asset' => (float) ($row['previous_year_percent_asset'] ?? 0),
        ];
    })->values();

    $reportPayload = [
        'company' => [
            'name' => auth()->user()?->company?->name ?? 'Company',
        ],
        'filters' => [
            'year' => $currentYear,
            'periodLabel' => ucfirst($periodLabel),
            'status' => strtoupper((string) ($filters['status'] ?? 'POSTED')),
        ],
        'generatedAt' => $printedDate,
        'rows' => $rowsForPdf,
    ];
@endphp

<div class="page">
    <div class="toolbar">
        <button onclick="window.print()">Print / Save PDF</button>
    </div>

    <div class="report-header">
        <p class="company-name" id="companyName"></p>
        <h1 class="report-title">Laporan Neraca</h1>
        <div class="report-subtitle" id="reportSubtitle"></div>
    </div>

    <div class="report-meta">
        <div class="left" id="metaLeft"></div>
        <div class="right" id="metaRight"></div>
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
                <th rowspan="2" class="left-text">COA Level 3</th>
                <th colspan="2" class="right-text" id="currentMonthHead"></th>
                <th colspan="2" class="right-text" id="yearToDateHead"></th>
                <th colspan="2" class="right-text" id="lastYearToDateHead"></th>
            </tr>
            <tr class="sub-header">
                <th class="right-text">Amount</th>
                <th class="right-text">% Asset</th>
                <th class="right-text">Amount</th>
                <th class="right-text">% Asset</th>
                <th class="right-text">Amount</th>
                <th class="right-text">% Asset</th>
            </tr>
        </thead>
        <tbody id="reportBody"></tbody>
    </table>
</div>

<script>
    const report = @json($reportPayload);

    const segmentOrder = ["asset", "liability", "equity", "current_year_profit"];
    const segmentLabels = {
        asset: "Asset",
        liability: "Liability",
        equity: "Equity",
        current_year_profit: "Current Year Profit"
    };

    function formatAmount(value) {
        const abs = Math.abs(Number(value));
        const str = abs.toLocaleString("id-ID", { maximumFractionDigits: 0 });
        return Number(value) < 0 ? `(${str})` : str;
    }

    function formatPercent(value) {
        return `${Number(value).toLocaleString("id-ID", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        })}%`;
    }

    function sum(rows, key) {
        return rows.reduce((acc, row) => acc + Number(row[key] || 0), 0);
    }

    function classByValue(value, usePositive = false) {
        if (Number(value) < 0) return "negative";
        if (usePositive && Number(value) > 0) return "positive";
        return "";
    }

    function groupBy(array, key) {
        return array.reduce((acc, item) => {
            const group = item[key] ?? "";
            if (!acc[group]) acc[group] = [];
            acc[group].push(item);
            return acc;
        }, {});
    }

    function renderHeader() {
        const currentYear = Number(report.filters.year);
        const previousYear = currentYear - 1;
        const periodLabel = report.filters.periodLabel ?? "-";
        const monthOnlyLabel = String(periodLabel).split(" ")[0];

        document.getElementById("companyName").textContent = report.company.name;
        document.getElementById("reportSubtitle").textContent =
            `Periode: ${report.filters.periodLabel} | Perbandingan ${currentYear} vs ${previousYear}`;

        document.getElementById("metaLeft").innerHTML = `
            <div><strong>Tahun Berjalan:</strong> ${currentYear}</div>
            <div><strong>Tahun Pembanding:</strong> ${previousYear}</div>
            <div><strong>COA Ditampilkan:</strong> Level 3</div>
        `;

        document.getElementById("metaRight").innerHTML = `
            <div><strong>Status Jurnal:</strong> ${report.filters.status}</div>
            <div><strong>Dicetak:</strong> ${report.generatedAt}</div>
        `;

        document.getElementById("currentMonthHead").textContent = `Current Month (Jan-${monthOnlyLabel} ${currentYear})`;
        document.getElementById("yearToDateHead").textContent = `Year to Date (Jan-${monthOnlyLabel} ${currentYear})`;
        document.getElementById("lastYearToDateHead").textContent = `Last Year to Date (Jan-${monthOnlyLabel} ${previousYear})`;
    }

    function prepareData() {
        const rows = report.rows.map(row => ({ ...row }));
        const totalAssetCurrent = sum(rows.filter(r => r.segment_key === "asset"), "current_year");
        const totalAssetPrevious = sum(rows.filter(r => r.segment_key === "asset"), "previous_year");
        rows.forEach((row) => {
            row.current_year_percent_asset = totalAssetCurrent !== 0 ? (Number(row.current_year) / totalAssetCurrent) * 100 : 0;
            row.previous_year_percent_asset = totalAssetPrevious !== 0 ? (Number(row.previous_year) / totalAssetPrevious) * 100 : 0;
        });

        return {
            rows,
            summary: {
                total_asset_current_year: totalAssetCurrent,
                total_asset_previous_year: totalAssetPrevious,
                total_liability_current_year: sum(rows.filter(r => r.segment_key === "liability"), "current_year"),
                total_liability_previous_year: sum(rows.filter(r => r.segment_key === "liability"), "previous_year"),
                total_equity_current_year: sum(rows.filter(r => r.segment_key === "equity"), "current_year"),
                total_equity_previous_year: sum(rows.filter(r => r.segment_key === "equity"), "previous_year"),
                total_profit_current_year: sum(rows.filter(r => r.segment_key === "current_year_profit"), "current_year"),
                total_profit_previous_year: sum(rows.filter(r => r.segment_key === "current_year_profit"), "previous_year")
            }
        };
    }

    function renderReport() {
        const { rows, summary } = prepareData();
        const body = document.getElementById("reportBody");

        const totalRightCurrent =
            summary.total_liability_current_year +
            summary.total_equity_current_year +
            summary.total_profit_current_year;

        const totalRightPrevious =
            summary.total_liability_previous_year +
            summary.total_equity_previous_year +
            summary.total_profit_previous_year;

        const balanceCurrent = summary.total_asset_current_year - totalRightCurrent;
        const balancePrevious = summary.total_asset_previous_year - totalRightPrevious;

        const rowsBySegment = groupBy(rows, "segment_key");
        let html = "";

        segmentOrder.forEach(segment => {
            const segmentRows = rowsBySegment[segment] || [];
            if (!segmentRows.length) return;

            const segmentCurrent = sum(segmentRows, "current_year");
            const segmentPrevious = sum(segmentRows, "previous_year");
            html += `
                <tr class="segment-header">
                    <td colspan="7">${segmentLabels[segment] ?? segment}</td>
                </tr>
            `;

            const groupedBySubgroup = groupBy(segmentRows, "subgroup_key");
            Object.entries(groupedBySubgroup).forEach(([subgroupKey, subgroupRows]) => {
                if (!subgroupRows.length) return;

                const subgroupLabel = subgroupRows[0].subgroup_label || subgroupKey;
                const subCurrent = sum(subgroupRows, "current_year");
                const subPrevious = sum(subgroupRows, "previous_year");
                html += `
                    <tr class="sub-header-row">
                        <td colspan="7">${subgroupLabel}</td>
                    </tr>
                `;

                subgroupRows.forEach(row => {
                    html += `
                        <tr class="detail">
                            <td class="left-text">${row.coa_level_3}</td>

                            <td class="right-text ${classByValue(row.current_year)}">${formatAmount(row.current_year)}</td>
                            <td class="right-text">${formatPercent(row.current_year_percent_asset)}</td>

                            <td class="right-text ${classByValue(row.current_year)}">${formatAmount(row.current_year)}</td>
                            <td class="right-text">${formatPercent(row.current_year_percent_asset)}</td>

                            <td class="right-text ${classByValue(row.previous_year)}">${formatAmount(row.previous_year)}</td>
                            <td class="right-text">${formatPercent(row.previous_year_percent_asset)}</td>
                        </tr>
                    `;
                });

                html += `
                    <tr class="subtotal">
                        <td class="left-text">Total ${subgroupLabel}</td>

                        <td class="right-text ${classByValue(subCurrent)}">${formatAmount(subCurrent)}</td>
                        <td class="right-text">${formatPercent(summary.total_asset_current_year !== 0 ? (subCurrent / summary.total_asset_current_year) * 100 : 0)}</td>

                        <td class="right-text ${classByValue(subCurrent)}">${formatAmount(subCurrent)}</td>
                        <td class="right-text">${formatPercent(summary.total_asset_current_year !== 0 ? (subCurrent / summary.total_asset_current_year) * 100 : 0)}</td>

                        <td class="right-text ${classByValue(subPrevious)}">${formatAmount(subPrevious)}</td>
                        <td class="right-text">${formatPercent(summary.total_asset_previous_year !== 0 ? (subPrevious / summary.total_asset_previous_year) * 100 : 0)}</td>
                    </tr>
                `;
            });

            html += `
                <tr class="total-segment">
                    <td class="left-text">Total ${segmentLabels[segment]}</td>

                    <td class="right-text ${classByValue(segmentCurrent)}">${formatAmount(segmentCurrent)}</td>
                    <td class="right-text">${formatPercent(summary.total_asset_current_year !== 0 ? (segmentCurrent / summary.total_asset_current_year) * 100 : 0)}</td>

                    <td class="right-text ${classByValue(segmentCurrent)}">${formatAmount(segmentCurrent)}</td>
                    <td class="right-text">${formatPercent(summary.total_asset_current_year !== 0 ? (segmentCurrent / summary.total_asset_current_year) * 100 : 0)}</td>

                    <td class="right-text ${classByValue(segmentPrevious)}">${formatAmount(segmentPrevious)}</td>
                    <td class="right-text">${formatPercent(summary.total_asset_previous_year !== 0 ? (segmentPrevious / summary.total_asset_previous_year) * 100 : 0)}</td>
                </tr>
            `;
        });

        html += `
            <tr class="subtotal">
                <td class="left-text">Total Asset</td>
                <td class="right-text ${classByValue(summary.total_asset_current_year)}">${formatAmount(summary.total_asset_current_year)}</td>
                <td class="right-text">100,00%</td>
                <td class="right-text ${classByValue(summary.total_asset_current_year)}">${formatAmount(summary.total_asset_current_year)}</td>
                <td class="right-text">100,00%</td>
                <td class="right-text ${classByValue(summary.total_asset_previous_year)}">${formatAmount(summary.total_asset_previous_year)}</td>
                <td class="right-text">100,00%</td>
            </tr>

            <tr class="subtotal">
                <td class="left-text">Total Liability + Equity + Current Year Profit</td>
                <td class="right-text ${classByValue(totalRightCurrent)}">${formatAmount(totalRightCurrent)}</td>
                <td class="right-text">${formatPercent(summary.total_asset_current_year !== 0 ? (totalRightCurrent / summary.total_asset_current_year) * 100 : 0)}</td>
                <td class="right-text ${classByValue(totalRightCurrent)}">${formatAmount(totalRightCurrent)}</td>
                <td class="right-text">${formatPercent(summary.total_asset_current_year !== 0 ? (totalRightCurrent / summary.total_asset_current_year) * 100 : 0)}</td>
                <td class="right-text ${classByValue(totalRightPrevious)}">${formatAmount(totalRightPrevious)}</td>
                <td class="right-text">${formatPercent(summary.total_asset_previous_year !== 0 ? (totalRightPrevious / summary.total_asset_previous_year) * 100 : 0)}</td>
            </tr>

            <tr class="grand-total">
                <td class="left-text">Balance Check</td>
                <td class="right-text ${classByValue(balanceCurrent)}">${formatAmount(balanceCurrent)}</td>
                <td class="right-text">${formatPercent(summary.total_asset_current_year !== 0 ? (balanceCurrent / summary.total_asset_current_year) * 100 : 0)}</td>
                <td class="right-text ${classByValue(balanceCurrent)}">${formatAmount(balanceCurrent)}</td>
                <td class="right-text">${formatPercent(summary.total_asset_current_year !== 0 ? (balanceCurrent / summary.total_asset_current_year) * 100 : 0)}</td>
                <td class="right-text ${classByValue(balancePrevious)}">${formatAmount(balancePrevious)}</td>
                <td class="right-text">${formatPercent(summary.total_asset_previous_year !== 0 ? (balancePrevious / summary.total_asset_previous_year) * 100 : 0)}</td>
            </tr>
        `;

        body.innerHTML = html;
    }

    renderHeader();
    renderReport();
</script>

</body>
</html>
