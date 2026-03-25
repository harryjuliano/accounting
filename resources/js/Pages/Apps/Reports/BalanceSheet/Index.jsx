import AppLayout from '@/Layouts/AppLayout';
import { Head, router, usePage } from '@inertiajs/react';
import React from 'react';
import Table from '@/Components/Table';
import { IconChevronDown, IconChevronRight, IconDatabaseOff } from '@tabler/icons-react';

const formatAmount = (value) => new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
}).format(Number(value || 0));

const formatPercent = (value) => `${new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
}).format(Number(value || 0))}%`;

const monthOptions = [
    { value: 1, label: 'Jan' },
    { value: 2, label: 'Feb' },
    { value: 3, label: 'Mar' },
    { value: 4, label: 'Apr' },
    { value: 5, label: 'Mei' },
    { value: 6, label: 'Jun' },
    { value: 7, label: 'Jul' },
    { value: 8, label: 'Agu' },
    { value: 9, label: 'Sep' },
    { value: 10, label: 'Okt' },
    { value: 11, label: 'Nov' },
    { value: 12, label: 'Des' },
];

const getGeneralLedgerLink = (filters, coaId) => {
    const periodStart = new Date(Date.UTC(Number(filters.year), 0, 1));
    const periodEnd = new Date(Date.UTC(Number(filters.year), Number(filters.period), 0));

    return route('apps.reports.general-ledger', {
        year: filters.year,
        date_from: periodStart.toISOString().slice(0, 10),
        date_to: periodEnd.toISOString().slice(0, 10),
        company_id: filters.company_id,
        branch_id: filters.branch_id,
        coa_id: coaId,
        status: filters.status,
    });
};

const toNumber = (value) => Number(value || 0);
const hasValue = (value) => value !== null && value !== undefined && `${value}`.trim() !== '';

const buildFlatRows = (rows, totalAssetCurrent, totalAssetPrevious) => {
    const map = new Map();
    const order = [];

    const ensureRow = (id, parentId, level, label, kind = 'group', segment = null) => {
        if (!map.has(id)) {
            const next = {
                id,
                parentId,
                level,
                label: label || '-',
                kind,
                segment,
                current_year: 0,
                previous_year: 0,
                current_year_percent_asset: 0,
                previous_year_percent_asset: 0,
                isLeaf: false,
                coa_id: null,
                coa_code: null,
            };
            map.set(id, next);
            order.push(id);
        }

        return map.get(id);
    };

    rows.forEach((row, index) => {
        const currentYear = toNumber(row.current_year);
        const previousYear = toNumber(row.previous_year);
        const segmentKey = row.segment_key ?? 'other';
        const segmentLabel = row.segment ?? 'Other';

        const segmentNode = ensureRow(`segment-${segmentKey}`, null, 0, segmentLabel, segmentKey, segmentLabel);
        segmentNode.current_year += currentYear;
        segmentNode.previous_year += previousYear;

        let parentId = segmentNode.id;
        const parents = [segmentNode];

        if (hasValue(row.coa_level_1)) {
            const level1Id = `l1-${segmentKey}-${row.coa_level_1_id ?? row.coa_level_1 ?? index}`;
            const level1 = ensureRow(level1Id, parentId, 1, row.coa_level_1, 'group', segmentLabel);
            parents.push(level1);
            parentId = level1Id;
        }

        if (hasValue(row.coa_level_2)) {
            const level2Id = `l2-${segmentKey}-${row.coa_level_2_id ?? `${parentId}-${row.coa_level_2}`}`;
            const level2 = ensureRow(level2Id, parentId, 2, row.coa_level_2, 'group', segmentLabel);
            parents.push(level2);
            parentId = level2Id;
        }

        if (hasValue(row.coa_level_3)) {
            const level3Id = `l3-${segmentKey}-${row.coa_level_3_id ?? `${parentId}-${row.coa_level_3}`}`;
            const level3 = ensureRow(level3Id, parentId, 3, row.coa_level_3, 'group', segmentLabel);
            parents.push(level3);
            parentId = level3Id;
        }

        const leafLabel = row.coa_level_4 || row.coa_level_3 || row.coa_level_2 || row.coa_level_1 || `Account ${index + 1}`;
        const leafId = `leaf-${segmentKey}-${row.coa_id ?? row.coa_level_4_id ?? `${parentId}-${row.coa_code ?? index}`}`;
        const leaf = ensureRow(leafId, parentId, Math.max(parents.length, 1), leafLabel, 'detail', segmentLabel);

        parents.forEach((item) => {
            item.current_year += currentYear;
            item.previous_year += previousYear;
        });

        leaf.current_year = currentYear;
        leaf.previous_year = previousYear;
        leaf.isLeaf = true;
        leaf.coa_id = row.coa_id;
        leaf.coa_code = row.coa_code;
    });

    const calcPercent = (value, total) => (Math.abs(total) <= 0.000001 ? 0 : ((value / total) * 100));
    order.forEach((id) => {
        const item = map.get(id);
        item.current_year_percent_asset = calcPercent(item.current_year, totalAssetCurrent);
        item.previous_year_percent_asset = calcPercent(item.previous_year, totalAssetPrevious);
    });

    return {
        order,
        map,
        childrenMap: order.reduce((carry, id) => {
            const item = map.get(id);
            const key = item.parentId ?? '__root__';
            if (!carry.has(key)) carry.set(key, []);
            carry.get(key).push(id);
            return carry;
        }, new Map()),
    };
};

const getRowTone = (row) => {
    const segment = `${row.segment_key ?? ''}`.toLowerCase();

    if (segment.includes('asset')) {
        return 'bg-blue-50/70 text-blue-900 dark:bg-blue-950/30 dark:text-blue-100';
    }

    if (segment.includes('liability')) {
        return 'bg-amber-50/70 text-amber-900 dark:bg-amber-950/30 dark:text-amber-100';
    }

    if (segment.includes('equity')) {
        return 'bg-violet-50/70 text-violet-900 dark:bg-violet-950/30 dark:text-violet-100';
    }

    if (segment.includes('current_year_profit')) {
        return 'bg-emerald-50/70 text-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-100';
    }

    return 'bg-white text-gray-800 dark:bg-gray-950 dark:text-gray-100';
};

const getAmountClass = (value) => Number(value || 0) < 0
    ? 'text-right font-medium text-rose-600 dark:text-rose-300'
    : 'text-right font-medium text-gray-800 dark:text-gray-100';

export default function Index() {
    const { rows, summary, companies, branches, statusOptions, filters, yearOptions = [] } = usePage().props;
    const now = new Date();
    const fallbackYear = Number(filters?.year ?? now.getFullYear());
    const resolvedYear = yearOptions.includes(fallbackYear)
        ? fallbackYear
        : (yearOptions[0] ?? fallbackYear);
    const fallbackPeriod = Number(filters?.period ?? (resolvedYear === now.getFullYear() ? (now.getMonth() + 1) : 12));

    const [listFilters, setListFilters] = React.useState({
        company_id: `${filters?.company_id ?? 'all'}`,
        branch_id: `${filters?.branch_id ?? 'all'}`,
        status: filters?.status ?? 'posted',
        year: resolvedYear,
        period: fallbackPeriod,
        drill_level: 4,
    });
    const [viewMode, setViewMode] = React.useState('detail');
    const [expandedNodes, setExpandedNodes] = React.useState({});

    const applyFilters = React.useCallback((nextFilters) => {
        router.get(route('apps.reports.balance-sheet'), nextFilters, {
            preserveState: true,
            replace: true,
        });
    }, []);

    const updateFilter = (field, value) => {
        const nextFilters = { ...listFilters, [field]: value, drill_level: 4 };
        setListFilters(nextFilters);
        applyFilters(nextFilters);
    };
    const exportPdf = () => {
        const query = new URLSearchParams({
            ...Object.entries(listFilters).reduce((carry, [key, value]) => ({
                ...carry,
                [key]: `${value}`,
            }), {}),
            export: 'pdf',
        });

        window.open(`${route('apps.reports.balance-sheet')}?${query.toString()}`, '_blank');
    };

    const branchOptions = branches.filter((branch) => listFilters.company_id === 'all' || Number(branch.company_id) === Number(listFilters.company_id));
    const totalAssetCurrentYear = Number(summary?.total_asset_current_year || 0);
    const totalAssetPreviousYear = Number(summary?.total_asset_previous_year || 0);
    const totalLiabilityEquityProfitCurrentYear = Number(summary?.total_right_side_current_year || 0);
    const totalLiabilityEquityProfitPreviousYear = Number(summary?.total_right_side_previous_year || 0);
    const balanceCurrentYear = totalAssetCurrentYear - totalLiabilityEquityProfitCurrentYear;
    const balancePreviousYear = totalAssetPreviousYear - totalLiabilityEquityProfitPreviousYear;
    const selectedMonthLabel = monthOptions.find((item) => item.value === Number(listFilters.period))?.label ?? '-';
    const normalizedRows = React.useMemo(
        () => buildFlatRows(rows, totalAssetCurrentYear, totalAssetPreviousYear),
        [rows, totalAssetCurrentYear, totalAssetPreviousYear],
    );

    React.useEffect(() => {
        const defaults = {};
        normalizedRows.order.forEach((id) => {
            const row = normalizedRows.map.get(id);
            if (!row || row.isLeaf) return;
            if ((normalizedRows.childrenMap.get(id) || []).length > 0) {
                defaults[id] = row.level <= 1;
            }
        });
        setExpandedNodes(defaults);
    }, [normalizedRows]);

    const hasChildren = React.useCallback((id) => (normalizedRows.childrenMap.get(id) || []).length > 0, [normalizedRows]);

    const isVisible = React.useCallback((row) => {
        if (!row.parentId) return true;
        let currentParentId = row.parentId;
        while (currentParentId) {
            if (!expandedNodes[currentParentId]) return false;
            currentParentId = normalizedRows.map.get(currentParentId)?.parentId ?? null;
        }

        return true;
    }, [expandedNodes, normalizedRows]);

    const visibleRows = React.useMemo(() => {
        const base = normalizedRows.order
            .map((id) => normalizedRows.map.get(id))
            .filter(Boolean)
            .filter(isVisible);

        if (viewMode === 'summary') {
            return base.filter((row) => !row.isLeaf);
        }

        return base;
    }, [isVisible, normalizedRows, viewMode]);

    const toggleNode = (id) => {
        setExpandedNodes((prev) => ({ ...prev, [id]: !prev[id] }));
    };

    const expandAll = () => {
        const next = {};
        normalizedRows.order.forEach((id) => {
            if (hasChildren(id)) next[id] = true;
        });
        setExpandedNodes(next);
    };

    const collapseAll = () => {
        const next = {};
        normalizedRows.order.forEach((id) => {
            if (hasChildren(id)) next[id] = false;
        });
        setExpandedNodes(next);
    };

    const safePercentOfAsset = (value, totalAsset) => (Math.abs(totalAsset) > 0.000001
        ? (Number(value || 0) / totalAsset) * 100
        : 0);

    return (
        <AppLayout>
            <Head title='Laporan Neraca' />
            <div className='p-6'>
                <div className='mb-4'>
                    <h1 className='text-xl font-semibold text-gray-800 dark:text-gray-100'>Laporan Neraca</h1>
                    <p className='text-sm text-gray-500 dark:text-gray-400'>Laporan Keuangan &gt; Neraca</p>
                </div>

                <div className='rounded-lg border bg-white p-4 dark:border-gray-900 dark:bg-gray-950'>
                    <div className='grid grid-cols-1 gap-3 md:grid-cols-7'>
                        <div>
                            <label className='mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300'>Company</label>
                            <select className='w-full rounded border-gray-300 bg-white text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100' value={listFilters.company_id} onChange={(e) => updateFilter('company_id', e.target.value)}>
                                <option value='all'>All Company</option>
                                {companies.map((company) => <option key={company.id} value={company.id}>{company.name}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className='mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300'>Branch</label>
                            <select className='w-full rounded border-gray-300 bg-white text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100' value={listFilters.branch_id} onChange={(e) => updateFilter('branch_id', e.target.value)}>
                                <option value='all'>All Branch</option>
                                {branchOptions.map((branch) => <option key={branch.id} value={branch.id}>{branch.code} - {branch.name}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className='mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300'>Status</label>
                            <select className='w-full rounded border-gray-300 bg-white text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100' value={listFilters.status} onChange={(e) => updateFilter('status', e.target.value)}>
                                {statusOptions.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className='mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300'>Year</label>
                            <select className='w-full rounded border-gray-300 bg-white text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100' value={listFilters.year} onChange={(e) => updateFilter('year', Number(e.target.value))}>
                                {yearOptions.map((year) => <option key={year} value={year}>{year}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className='mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300'>Periode</label>
                            <select className='w-full rounded border-gray-300 bg-white text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100' value={listFilters.period} onChange={(e) => updateFilter('period', Number(e.target.value))}>
                                {monthOptions.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
                            </select>
                        </div>
                        <div className='flex items-end'>
                            <button
                                type='button'
                                onClick={exportPdf}
                                className='w-full rounded bg-blue-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-blue-700'
                            >
                                Export PDF (3 Kolom)
                            </button>
                        </div>
                    </div>
                </div>

                <div className='mt-4 overflow-hidden rounded-lg border bg-white dark:border-gray-900 dark:bg-gray-950'>
                    <div className='border-b border-gray-200 p-3 dark:border-gray-800'>
                        <div className='flex flex-wrap items-center gap-2'>
                            <button type='button' onClick={expandAll} className='rounded border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-900'>Expand All</button>
                            <button type='button' onClick={collapseAll} className='rounded border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-900'>Collapse All</button>
                            <button type='button' onClick={() => setViewMode('detail')} className={`rounded px-3 py-1.5 text-xs font-semibold ${viewMode === 'detail' ? 'bg-blue-600 text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-900'}`}>Show Detail</button>
                            <button type='button' onClick={() => setViewMode('summary')} className={`rounded px-3 py-1.5 text-xs font-semibold ${viewMode === 'summary' ? 'bg-blue-600 text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-900'}`}>Show Summary</button>
                        </div>
                    </div>
                    <div className='max-h-[65vh] overflow-auto'>
                        <Table className='overflow-visible rounded-none border-0'>
                            <Table.Thead>
                                <tr>
                                    <Table.Th className='sticky top-0 z-30 w-[180px] bg-gray-50 dark:bg-gray-950'>Segment</Table.Th>
                                    <Table.Th className='sticky top-0 z-30 min-w-[320px] bg-gray-50 dark:bg-gray-950'>COA</Table.Th>
                                    <Table.Th className='sticky top-0 z-30 bg-gray-50 text-right dark:bg-gray-950'>Current Month (Jan-{selectedMonthLabel} {listFilters.year})</Table.Th>
                                    <Table.Th className='sticky top-0 z-30 bg-gray-50 text-right dark:bg-gray-950'>% Total Asset</Table.Th>
                                    <Table.Th className='sticky top-0 z-30 bg-gray-50 text-right dark:bg-gray-950'>Year to Date (Jan-{selectedMonthLabel} {listFilters.year})</Table.Th>
                                    <Table.Th className='sticky top-0 z-30 bg-gray-50 text-right dark:bg-gray-950'>% Total Asset</Table.Th>
                                    <Table.Th className='sticky top-0 z-30 bg-gray-50 text-right dark:bg-gray-950'>Last Year to Date (Jan-{selectedMonthLabel} {Number(listFilters.year) - 1})</Table.Th>
                                    <Table.Th className='sticky top-0 z-30 bg-gray-50 text-right dark:bg-gray-950'>% Total Asset</Table.Th>
                                </tr>
                            </Table.Thead>
                            <Table.Tbody>
                                {visibleRows.length > 0 ? visibleRows.map((node) => {
                                    const isParent = !node.isLeaf;
                                    const canToggle = hasChildren(node.id);
                                    const leftPadding = 6 + (node.level * 24);
                                    const label = node.isLeaf && node.coa_code ? `${node.coa_code} - ${node.label}` : node.label;

                                    return (
                                        <tr key={node.id} className={getRowTone({ segment_key: node.kind })}>
                                            <Table.Td className='!py-3 font-semibold'>{node.level === 0 ? node.segment : ''}</Table.Td>
                                            <Table.Td className='!py-3'>
                                                <div className={`flex items-center gap-2 ${canToggle ? 'cursor-pointer' : ''}`} style={{ paddingLeft: `${leftPadding}px` }} onClick={() => canToggle && toggleNode(node.id)}>
                                                    {isParent && canToggle ? (
                                                        <button
                                                            type='button'
                                                            onClick={(e) => {
                                                                e.stopPropagation();
                                                                toggleNode(node.id);
                                                            }}
                                                            className='rounded border border-gray-300 p-0.5 text-gray-600 hover:bg-gray-200/70 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800/70'
                                                        >
                                                            {expandedNodes[node.id] ? <IconChevronDown size={14} /> : <IconChevronRight size={14} />}
                                                        </button>
                                                    ) : (
                                                        <span className='inline-block w-[18px]' />
                                                    )}
                                                    <span className={isParent ? 'font-semibold' : 'font-normal'}>{label}</span>
                                                </div>
                                            </Table.Td>
                                            <Table.Td className={`!py-3 ${getAmountClass(node.current_year)}`}>
                                                {node.isLeaf && node.coa_id
                                                    ? <a href={getGeneralLedgerLink(listFilters, node.coa_id)} className='hover:underline' onClick={(e) => e.stopPropagation()}>{formatAmount(node.current_year)}</a>
                                                    : formatAmount(node.current_year)}
                                            </Table.Td>
                                            <Table.Td className='!py-3 text-right'>{formatPercent(node.current_year_percent_asset)}</Table.Td>
                                            <Table.Td className={`!py-3 ${getAmountClass(node.current_year)}`}>
                                                {node.isLeaf && node.coa_id
                                                    ? <a href={getGeneralLedgerLink(listFilters, node.coa_id)} className='hover:underline' onClick={(e) => e.stopPropagation()}>{formatAmount(node.current_year)}</a>
                                                    : formatAmount(node.current_year)}
                                            </Table.Td>
                                            <Table.Td className='!py-3 text-right'>{formatPercent(node.current_year_percent_asset)}</Table.Td>
                                            <Table.Td className={`!py-3 ${getAmountClass(node.previous_year)}`}>
                                                {node.isLeaf && node.coa_id
                                                    ? <a href={getGeneralLedgerLink(listFilters, node.coa_id)} className='hover:underline' onClick={(e) => e.stopPropagation()}>{formatAmount(node.previous_year)}</a>
                                                    : formatAmount(node.previous_year)}
                                            </Table.Td>
                                            <Table.Td className='!py-3 text-right'>{formatPercent(node.previous_year_percent_asset)}</Table.Td>
                                        </tr>
                                    );
                                }) : (
                                    <Table.Empty colSpan={8} message={(
                                        <div className='flex flex-col items-center gap-1 text-sm text-gray-500 dark:text-gray-300'>
                                            <IconDatabaseOff size={24} />
                                            <span>Data Laporan Neraca tidak ditemukan.</span>
                                        </div>
                                    )}
                                    />
                                )}
                                {rows.length > 0 && (
                                    <>
                                        <tr className='bg-gray-100/80 dark:bg-gray-900/70'>
                                            <Table.Td colSpan={8} className='sticky bottom-[156px] z-20 py-2 text-sm font-semibold text-gray-700 dark:text-gray-200'>
                                                Balance Check: Asset = Liability + Equity + Current Year Profit
                                            </Table.Td>
                                        </tr>
                                        <tr className='bg-slate-100/90 text-slate-900 dark:bg-slate-900 dark:text-slate-100'>
                                            <Table.Td className='sticky bottom-[104px] z-20 bg-slate-100/95 font-semibold dark:bg-slate-900'>Asset</Table.Td>
                                            <Table.Td className='sticky bottom-[104px] z-20 bg-slate-100/95 font-semibold dark:bg-slate-900'>Total Asset</Table.Td>
                                            <Table.Td className={`sticky bottom-[104px] z-20 bg-slate-100/95 ${getAmountClass(totalAssetCurrentYear)} dark:bg-slate-900`}>{formatAmount(totalAssetCurrentYear)}</Table.Td>
                                            <Table.Td className='sticky bottom-[104px] z-20 bg-slate-100/95 text-right dark:bg-slate-900'>100.00%</Table.Td>
                                            <Table.Td className={`sticky bottom-[104px] z-20 bg-slate-100/95 ${getAmountClass(totalAssetCurrentYear)} dark:bg-slate-900`}>{formatAmount(totalAssetCurrentYear)}</Table.Td>
                                            <Table.Td className='sticky bottom-[104px] z-20 bg-slate-100/95 text-right dark:bg-slate-900'>100.00%</Table.Td>
                                            <Table.Td className={`sticky bottom-[104px] z-20 bg-slate-100/95 ${getAmountClass(totalAssetPreviousYear)} dark:bg-slate-900`}>{formatAmount(totalAssetPreviousYear)}</Table.Td>
                                            <Table.Td className='sticky bottom-[104px] z-20 bg-slate-100/95 text-right dark:bg-slate-900'>100.00%</Table.Td>
                                        </tr>
                                        <tr className='bg-slate-100/90 text-slate-900 dark:bg-slate-900 dark:text-slate-100'>
                                            <Table.Td className='sticky bottom-[52px] z-20 bg-slate-100/95 font-semibold dark:bg-slate-900'>Liability + Equity + Current Year Profit</Table.Td>
                                            <Table.Td className='sticky bottom-[52px] z-20 bg-slate-100/95 font-semibold dark:bg-slate-900'>Total Liability + Equity + Current Year Profit</Table.Td>
                                            <Table.Td className={`sticky bottom-[52px] z-20 bg-slate-100/95 ${getAmountClass(totalLiabilityEquityProfitCurrentYear)} dark:bg-slate-900`}>{formatAmount(totalLiabilityEquityProfitCurrentYear)}</Table.Td>
                                            <Table.Td className='sticky bottom-[52px] z-20 bg-slate-100/95 text-right dark:bg-slate-900'>{formatPercent(safePercentOfAsset(totalLiabilityEquityProfitCurrentYear, totalAssetCurrentYear))}</Table.Td>
                                            <Table.Td className={`sticky bottom-[52px] z-20 bg-slate-100/95 ${getAmountClass(totalLiabilityEquityProfitCurrentYear)} dark:bg-slate-900`}>{formatAmount(totalLiabilityEquityProfitCurrentYear)}</Table.Td>
                                            <Table.Td className='sticky bottom-[52px] z-20 bg-slate-100/95 text-right dark:bg-slate-900'>{formatPercent(safePercentOfAsset(totalLiabilityEquityProfitCurrentYear, totalAssetCurrentYear))}</Table.Td>
                                            <Table.Td className={`sticky bottom-[52px] z-20 bg-slate-100/95 ${getAmountClass(totalLiabilityEquityProfitPreviousYear)} dark:bg-slate-900`}>{formatAmount(totalLiabilityEquityProfitPreviousYear)}</Table.Td>
                                            <Table.Td className='sticky bottom-[52px] z-20 bg-slate-100/95 text-right dark:bg-slate-900'>{formatPercent(safePercentOfAsset(totalLiabilityEquityProfitPreviousYear, totalAssetPreviousYear))}</Table.Td>
                                        </tr>
                                        <tr className='bg-slate-100/90 text-slate-900 dark:bg-slate-900 dark:text-slate-100'>
                                            <Table.Td className='sticky bottom-0 z-20 bg-slate-100/95 font-semibold dark:bg-slate-900'>Balance</Table.Td>
                                            <Table.Td className='sticky bottom-0 z-20 bg-slate-100/95 font-semibold dark:bg-slate-900'>Balance = Total Asset - (Total Liability + Equity + Current Year Profit)</Table.Td>
                                            <Table.Td className={`sticky bottom-0 z-20 bg-slate-100/95 ${getAmountClass(balanceCurrentYear)} dark:bg-slate-900`}>{formatAmount(balanceCurrentYear)}</Table.Td>
                                            <Table.Td className='sticky bottom-0 z-20 bg-slate-100/95 text-right dark:bg-slate-900'>{formatPercent(safePercentOfAsset(balanceCurrentYear, totalAssetCurrentYear))}</Table.Td>
                                            <Table.Td className={`sticky bottom-0 z-20 bg-slate-100/95 ${getAmountClass(balanceCurrentYear)} dark:bg-slate-900`}>{formatAmount(balanceCurrentYear)}</Table.Td>
                                            <Table.Td className='sticky bottom-0 z-20 bg-slate-100/95 text-right dark:bg-slate-900'>{formatPercent(safePercentOfAsset(balanceCurrentYear, totalAssetCurrentYear))}</Table.Td>
                                            <Table.Td className={`sticky bottom-0 z-20 bg-slate-100/95 ${getAmountClass(balancePreviousYear)} dark:bg-slate-900`}>{formatAmount(balancePreviousYear)}</Table.Td>
                                            <Table.Td className='sticky bottom-0 z-20 bg-slate-100/95 text-right dark:bg-slate-900'>{formatPercent(safePercentOfAsset(balancePreviousYear, totalAssetPreviousYear))}</Table.Td>
                                        </tr>
                                    </>
                                )}
                            </Table.Tbody>
                        </Table>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
