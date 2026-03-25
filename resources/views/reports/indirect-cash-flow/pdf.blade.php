<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Laporan Arus Kas Tidak Langsung</title>

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

    .report-table col.desc   { width: 40%; }
    .report-table col.amt    { width: 15%; }
    .report-table col.pct    { width: 8%; }

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

    .section-header td {
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

<div class="page">
    <div class="toolbar">
        <button onclick="window.print()">Print / Save PDF</button>
    </div>

    <div class="report-header">
        <p class="company-name" id="companyName"></p>
        <h1 class="report-title">Laporan Arus Kas - Metode Tidak Langsung</h1>
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
                <th rowspan="2" class="left-text">Uraian</th>
                <th colspan="2" class="right-text" id="yearCurrentHead"></th>
                <th colspan="2" class="right-text" id="yearPreviousHead"></th>
                <th colspan="2" class="right-text" id="varianceHead"></th>
            </tr>
            <tr class="sub-header">
                <th class="right-text">Amount</th>
                <th class="right-text">% of Sales</th>
                <th class="right-text">Amount</th>
                <th class="right-text">% of Sales</th>
                <th class="right-text">Amount</th>
                <th class="right-text">% of Sales</th>
            </tr>
        </thead>
        <tbody id="reportBody"></tbody>
    </table>
</div>

<script>
    const report = @json($report);

    const sectionLabels = {
        operating: "Cash Flow from Operating Activities",
        investing: "Cash Flow from Investing Activities",
        financing: "Cash Flow from Financing Activities"
    };

    const subgroupLabels = {
        operating: {
            profit_base: "Basis Profit",
            non_cash_adjustment: "Penyesuaian Non Kas",
            working_capital: "Perubahan Modal Kerja"
        },
        investing: {
            capex: "Capital Expenditure",
            disposal: "Disposal / Pelepasan Aset"
        },
        financing: {
            borrowing: "Pinjaman / Pembiayaan",
            equity: "Ekuitas / Dividen"
        }
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
            const group = item[key];
            if (!acc[group]) acc[group] = [];
            acc[group].push(item);
            return acc;
        }, {});
    }

    function renderHeader() {
        const currentYear = report.filters.year;
        const previousYear = currentYear - 1;

        document.getElementById("companyName").textContent = report.company.name;
        document.getElementById("reportSubtitle").textContent =
            `Periode: ${report.filters.periodLabel} | Perbandingan ${currentYear} vs ${previousYear}`;

        document.getElementById("metaLeft").innerHTML = `
            <div><strong>Tahun Berjalan:</strong> ${currentYear}</div>
            <div><strong>Tahun Pembanding:</strong> ${previousYear}</div>
        `;

        document.getElementById("metaRight").innerHTML = `
            <div><strong>Status Jurnal:</strong> ${report.filters.status}</div>
            <div><strong>Dicetak:</strong> {{ $generatedAt }}</div>
        `;

        document.getElementById("yearCurrentHead").textContent = currentYear;
        document.getElementById("yearPreviousHead").textContent = previousYear;
        document.getElementById("varianceHead").textContent = `Variance ${currentYear} vs ${previousYear}`;
    }

    function prepareData() {
        const rows = report.rows.map(row => ({ ...row }));
        const salesCurrent = Number(report.netSales.current || 0);
        const salesPrevious = Number(report.netSales.previous || 0);
        const salesVariance = salesCurrent - salesPrevious;

        rows.forEach(row => {
            row.variance = Number(row.current) - Number(row.previous);
            row.current_pct = salesCurrent !== 0 ? (Number(row.current) / salesCurrent) * 100 : 0;
            row.previous_pct = salesPrevious !== 0 ? (Number(row.previous) / salesPrevious) * 100 : 0;
            row.variance_pct = salesVariance !== 0 ? (Number(row.variance) / salesVariance) * 100 : 0;
        });

        const operatingRows = rows.filter(r => r.section === "operating");
        const investingRows = rows.filter(r => r.section === "investing");
        const financingRows = rows.filter(r => r.section === "financing");

        const netOperatingCurrent = sum(operatingRows, "current");
        const netOperatingPrevious = sum(operatingRows, "previous");
        const netOperatingVariance = netOperatingCurrent - netOperatingPrevious;

        const netInvestingCurrent = sum(investingRows, "current");
        const netInvestingPrevious = sum(investingRows, "previous");
        const netInvestingVariance = netInvestingCurrent - netInvestingPrevious;

        const netFinancingCurrent = sum(financingRows, "current");
        const netFinancingPrevious = sum(financingRows, "previous");
        const netFinancingVariance = netFinancingCurrent - netFinancingPrevious;

        const netIncreaseCurrent = netOperatingCurrent + netInvestingCurrent + netFinancingCurrent;
        const netIncreasePrevious = netOperatingPrevious + netInvestingPrevious + netFinancingPrevious;
        const netIncreaseVariance = netIncreaseCurrent - netIncreasePrevious;

        const beginningCashCurrent = Number(report.beginningCash.current || 0);
        const beginningCashPrevious = Number(report.beginningCash.previous || 0);
        const beginningCashVariance = beginningCashCurrent - beginningCashPrevious;

        const endingCashCurrent = Number(report.endingCash.current || 0);
        const endingCashPrevious = Number(report.endingCash.previous || 0);
        const endingCashVariance = endingCashCurrent - endingCashPrevious;

        return {
            rows,
            salesCurrent,
            salesPrevious,
            salesVariance,
            summary: {
                netOperatingCurrent,
                netOperatingPrevious,
                netOperatingVariance,
                netInvestingCurrent,
                netInvestingPrevious,
                netInvestingVariance,
                netFinancingCurrent,
                netFinancingPrevious,
                netFinancingVariance,
                netIncreaseCurrent,
                netIncreasePrevious,
                netIncreaseVariance,
                beginningCashCurrent,
                beginningCashPrevious,
                beginningCashVariance,
                endingCashCurrent,
                endingCashPrevious,
                endingCashVariance
            }
        };
    }

    function renderSection(sectionKey, rows, salesCurrent, salesPrevious, salesVariance) {
        const grouped = groupBy(rows, "subgroup");
        const subgroups = subgroupLabels[sectionKey] || {};
        let html = `
            <tr class="section-header">
                <td colspan="7">${sectionLabels[sectionKey]}</td>
            </tr>
        `;

        Object.keys(subgroups).forEach(subgroupKey => {
            const subgroupRows = grouped[subgroupKey] || [];
            if (!subgroupRows.length) return;

            const subCurrent = sum(subgroupRows, "current");
            const subPrevious = sum(subgroupRows, "previous");
            const subVariance = subCurrent - subPrevious;

            html += `
                <tr class="sub-header-row">
                    <td colspan="7">${subgroups[subgroupKey]}</td>
                </tr>
            `;

            subgroupRows.forEach(row => {
                html += `
                    <tr class="detail">
                        <td class="left-text">${row.label}</td>
                        <td class="right-text ${classByValue(row.current)}">${formatAmount(row.current)}</td>
                        <td class="right-text">${formatPercent(row.current_pct)}</td>
                        <td class="right-text ${classByValue(row.previous)}">${formatAmount(row.previous)}</td>
                        <td class="right-text">${formatPercent(row.previous_pct)}</td>
                        <td class="right-text ${classByValue(row.variance, true)}">${formatAmount(row.variance)}</td>
                        <td class="right-text ${classByValue(row.variance_pct, true)}">${formatPercent(row.variance_pct)}</td>
                    </tr>
                `;
            });

            html += `
                <tr class="subtotal">
                    <td class="left-text">Total ${subgroups[subgroupKey]}</td>
                    <td class="right-text ${classByValue(subCurrent)}">${formatAmount(subCurrent)}</td>
                    <td class="right-text">${formatPercent(salesCurrent !== 0 ? (subCurrent / salesCurrent) * 100 : 0)}</td>
                    <td class="right-text ${classByValue(subPrevious)}">${formatAmount(subPrevious)}</td>
                    <td class="right-text">${formatPercent(salesPrevious !== 0 ? (subPrevious / salesPrevious) * 100 : 0)}</td>
                    <td class="right-text ${classByValue(subVariance, true)}">${formatAmount(subVariance)}</td>
                    <td class="right-text ${classByValue(subVariance, true)}">${formatPercent(salesVariance !== 0 ? (subVariance / salesVariance) * 100 : 0)}</td>
                </tr>
            `;
        });

        return html;
    }

    function renderReport() {
        const { rows, salesCurrent, salesPrevious, salesVariance, summary } = prepareData();
        const body = document.getElementById("reportBody");

        const operatingRows = rows.filter(r => r.section === "operating");
        const investingRows = rows.filter(r => r.section === "investing");
        const financingRows = rows.filter(r => r.section === "financing");

        let html = "";
        html += renderSection("operating", operatingRows, salesCurrent, salesPrevious, salesVariance);
        html += `
            <tr class="subtotal"><td class="left-text">Net Cash from Operating Activities</td><td class="right-text ${classByValue(summary.netOperatingCurrent)}">${formatAmount(summary.netOperatingCurrent)}</td><td class="right-text">${formatPercent(salesCurrent !== 0 ? (summary.netOperatingCurrent / salesCurrent) * 100 : 0)}</td><td class="right-text ${classByValue(summary.netOperatingPrevious)}">${formatAmount(summary.netOperatingPrevious)}</td><td class="right-text">${formatPercent(salesPrevious !== 0 ? (summary.netOperatingPrevious / salesPrevious) * 100 : 0)}</td><td class="right-text ${classByValue(summary.netOperatingVariance, true)}">${formatAmount(summary.netOperatingVariance)}</td><td class="right-text ${classByValue(summary.netOperatingVariance, true)}">${formatPercent(salesVariance !== 0 ? (summary.netOperatingVariance / salesVariance) * 100 : 0)}</td></tr>
        `;

        html += renderSection("investing", investingRows, salesCurrent, salesPrevious, salesVariance);
        html += `
            <tr class="subtotal"><td class="left-text">Net Cash from Investing Activities</td><td class="right-text ${classByValue(summary.netInvestingCurrent)}">${formatAmount(summary.netInvestingCurrent)}</td><td class="right-text">${formatPercent(salesCurrent !== 0 ? (summary.netInvestingCurrent / salesCurrent) * 100 : 0)}</td><td class="right-text ${classByValue(summary.netInvestingPrevious)}">${formatAmount(summary.netInvestingPrevious)}</td><td class="right-text">${formatPercent(salesPrevious !== 0 ? (summary.netInvestingPrevious / salesPrevious) * 100 : 0)}</td><td class="right-text ${classByValue(summary.netInvestingVariance, true)}">${formatAmount(summary.netInvestingVariance)}</td><td class="right-text ${classByValue(summary.netInvestingVariance, true)}">${formatPercent(salesVariance !== 0 ? (summary.netInvestingVariance / salesVariance) * 100 : 0)}</td></tr>
        `;

        html += renderSection("financing", financingRows, salesCurrent, salesPrevious, salesVariance);
        html += `
            <tr class="subtotal"><td class="left-text">Net Cash from Financing Activities</td><td class="right-text ${classByValue(summary.netFinancingCurrent)}">${formatAmount(summary.netFinancingCurrent)}</td><td class="right-text">${formatPercent(salesCurrent !== 0 ? (summary.netFinancingCurrent / salesCurrent) * 100 : 0)}</td><td class="right-text ${classByValue(summary.netFinancingPrevious)}">${formatAmount(summary.netFinancingPrevious)}</td><td class="right-text">${formatPercent(salesPrevious !== 0 ? (summary.netFinancingPrevious / salesPrevious) * 100 : 0)}</td><td class="right-text ${classByValue(summary.netFinancingVariance, true)}">${formatAmount(summary.netFinancingVariance)}</td><td class="right-text ${classByValue(summary.netFinancingVariance, true)}">${formatPercent(salesVariance !== 0 ? (summary.netFinancingVariance / salesVariance) * 100 : 0)}</td></tr>
            <tr class="grand-total"><td class="left-text">Net Increase / (Decrease) in Cash and Cash Equivalents</td><td class="right-text ${classByValue(summary.netIncreaseCurrent)}">${formatAmount(summary.netIncreaseCurrent)}</td><td class="right-text">${formatPercent(salesCurrent !== 0 ? (summary.netIncreaseCurrent / salesCurrent) * 100 : 0)}</td><td class="right-text ${classByValue(summary.netIncreasePrevious)}">${formatAmount(summary.netIncreasePrevious)}</td><td class="right-text">${formatPercent(salesPrevious !== 0 ? (summary.netIncreasePrevious / salesPrevious) * 100 : 0)}</td><td class="right-text ${classByValue(summary.netIncreaseVariance, true)}">${formatAmount(summary.netIncreaseVariance)}</td><td class="right-text ${classByValue(summary.netIncreaseVariance, true)}">${formatPercent(salesVariance !== 0 ? (summary.netIncreaseVariance / salesVariance) * 100 : 0)}</td></tr>
            <tr class="subtotal"><td class="left-text">Cash and Cash Equivalents at Beginning of Year</td><td class="right-text ${classByValue(summary.beginningCashCurrent)}">${formatAmount(summary.beginningCashCurrent)}</td><td class="right-text">${formatPercent(salesCurrent !== 0 ? (summary.beginningCashCurrent / salesCurrent) * 100 : 0)}</td><td class="right-text ${classByValue(summary.beginningCashPrevious)}">${formatAmount(summary.beginningCashPrevious)}</td><td class="right-text">${formatPercent(salesPrevious !== 0 ? (summary.beginningCashPrevious / salesPrevious) * 100 : 0)}</td><td class="right-text ${classByValue(summary.beginningCashVariance, true)}">${formatAmount(summary.beginningCashVariance)}</td><td class="right-text ${classByValue(summary.beginningCashVariance, true)}">${formatPercent(salesVariance !== 0 ? (summary.beginningCashVariance / salesVariance) * 100 : 0)}</td></tr>
            <tr class="grand-total"><td class="left-text">Cash and Cash Equivalents at End of Year</td><td class="right-text ${classByValue(summary.endingCashCurrent)}">${formatAmount(summary.endingCashCurrent)}</td><td class="right-text">${formatPercent(salesCurrent !== 0 ? (summary.endingCashCurrent / salesCurrent) * 100 : 0)}</td><td class="right-text ${classByValue(summary.endingCashPrevious)}">${formatAmount(summary.endingCashPrevious)}</td><td class="right-text">${formatPercent(salesPrevious !== 0 ? (summary.endingCashPrevious / salesPrevious) * 100 : 0)}</td><td class="right-text ${classByValue(summary.endingCashVariance, true)}">${formatAmount(summary.endingCashVariance)}</td><td class="right-text ${classByValue(summary.endingCashVariance, true)}">${formatPercent(salesVariance !== 0 ? (summary.endingCashVariance / salesVariance) * 100 : 0)}</td></tr>
        `;

        body.innerHTML = html;
    }

    renderHeader();
    renderReport();
</script>

</body>
</html>
