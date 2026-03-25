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

const getParentTone = (label) => {
    const text = `${label ?? ''}`.toLowerCase();

    if (text.includes('pendapatan') || text.includes('revenue')) {
        return 'bg-blue-50/70 text-blue-900 dark:bg-blue-950/30 dark:text-blue-100';
    }

    if (text.includes('beban') || text.includes('expense') || text.includes('harga pokok') || text.includes('cogs')) {
        return 'bg-rose-50/70 text-rose-900 dark:bg-rose-950/30 dark:text-rose-100';
    }

    return 'bg-slate-50/80 text-slate-800 dark:bg-slate-900/80 dark:text-slate-100';
};

const getAmountClass = (value, isBold = false) => {
    const weightClass = isBold ? 'font-semibold' : 'font-medium';

    return Number(value || 0) < 0
        ? `text-right ${weightClass} text-rose-600 dark:text-rose-300`
        : `text-right ${weightClass} text-gray-800 dark:text-gray-100`;
};

const getGeneralLedgerLink = (filters, coaId) => {
    const periodStart = new Date(Date.UTC(Number(filters.year), Number(filters.period) - 1, 1));
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

const buildTreeRows = (rows) => {
    const groups = new Map();

    const ensureNode = (key, level, label, parentKey) => {
        if (!groups.has(key)) {
            groups.set(key, {
                key,
                parentKey,
                level,
                label: label || '-',
                current_month: 0,
                year_to_date: 0,
                last_year_to_date: 0,
                current_month_percent_sales: 0,
                year_to_date_percent_sales: 0,
                last_year_to_date_percent_sales: 0,
                children: new Set(),
                isLeaf: false,
                coa_id: null,
            });
        }

        return groups.get(key);
    };

    rows.forEach((row, index) => {
        const level1Key = `l1-${row.coa_level_1_id ?? row.coa_level_1 ?? index}`;
        const level2Key = `l2-${row.coa_level_2_id ?? `${level1Key}-${row.coa_level_2 ?? index}`}`;
        const level3Key = `l3-${row.coa_level_3_id ?? `${level2Key}-${row.coa_level_3 ?? index}`}`;
        const leafKey = `leaf-${row.coa_id ?? row.coa_level_4_id ?? `${level3Key}-${row.coa_code ?? index}`}`;

        const lvl1 = ensureNode(level1Key, 1, row.coa_level_1, null);
        const lvl2 = ensureNode(level2Key, 2, row.coa_level_2, level1Key);
        const lvl3 = ensureNode(level3Key, 3, row.coa_level_3, level2Key);
        const leaf = ensureNode(leafKey, 4, row.coa_level_4 || row.coa_code, level3Key);

        lvl1.children.add(level2Key);
        lvl2.children.add(level3Key);
        lvl3.children.add(leafKey);

        const currentMonth = toNumber(row.current_month);
        const ytd = toNumber(row.year_to_date);
        const lastYear = toNumber(row.last_year_to_date);

        [lvl1, lvl2, lvl3].forEach((node) => {
            node.current_month += currentMonth;
            node.year_to_date += ytd;
            node.last_year_to_date += lastYear;
        });

        leaf.current_month = currentMonth;
        leaf.year_to_date = ytd;
        leaf.last_year_to_date = lastYear;
        leaf.current_month_percent_sales = toNumber(row.current_month_percent_sales);
        leaf.year_to_date_percent_sales = toNumber(row.year_to_date_percent_sales);
        leaf.last_year_to_date_percent_sales = toNumber(row.last_year_to_date_percent_sales);
        leaf.coa_id = row.coa_id;
        leaf.coa_code = row.coa_code;
        leaf.isLeaf = true;
    });

    const level1Nodes = Array.from(groups.values()).filter((node) => node.level === 1);

    const calcPercent = (value, totalRevenue) => {
        if (Math.abs(totalRevenue) <= 0.000001) return 0;

        return (value / totalRevenue) * 100;
    };

    const totalRevenueCurrentMonth = level1Nodes
        .filter((node) => `${node.label}`.toLowerCase().includes('pendapatan') || `${node.label}`.toLowerCase().includes('revenue'))
        .reduce((carry, node) => carry + node.current_month, 0);
    const totalRevenueYtd = level1Nodes
        .filter((node) => `${node.label}`.toLowerCase().includes('pendapatan') || `${node.label}`.toLowerCase().includes('revenue'))
        .reduce((carry, node) => carry + node.year_to_date, 0);
    const totalRevenueLastYear = level1Nodes
        .filter((node) => `${node.label}`.toLowerCase().includes('pendapatan') || `${node.label}`.toLowerCase().includes('revenue'))
        .reduce((carry, node) => carry + node.last_year_to_date, 0);

    groups.forEach((node) => {
        if (node.isLeaf) return;
        node.current_month_percent_sales = calcPercent(node.current_month, totalRevenueCurrentMonth);
        node.year_to_date_percent_sales = calcPercent(node.year_to_date, totalRevenueYtd);
        node.last_year_to_date_percent_sales = calcPercent(node.last_year_to_date, totalRevenueLastYear);
    });

    return groups;
};

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
    const [viewMode, setViewMode] = React.useState('summary');
    const [expandedNodes, setExpandedNodes] = React.useState({});

    const applyFilters = React.useCallback((nextFilters) => {
        router.get(route('apps.reports.profit-loss'), nextFilters, {
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

        window.open(`${route('apps.reports.profit-loss')}?${query.toString()}`, '_blank');
    };

    const branchOptions = branches.filter((branch) => listFilters.company_id === 'all' || Number(branch.company_id) === Number(listFilters.company_id));
    const selectedMonthLabel = monthOptions.find((item) => item.value === Number(listFilters.period))?.label ?? '-';

    const treeMap = React.useMemo(() => buildTreeRows(rows), [rows]);
    const topLevelRows = React.useMemo(() => Array.from(treeMap.values()).filter((node) => node.level === 1), [treeMap]);

    React.useEffect(() => {
        const defaultExpanded = {};
        Array.from(treeMap.values())
            .filter((node) => !node.isLeaf)
            .forEach((node) => {
                defaultExpanded[node.key] = node.level <= 2;
            });
        setExpandedNodes(defaultExpanded);
    }, [treeMap]);

    const toggleNode = (key) => {
        setExpandedNodes((prev) => ({ ...prev, [key]: !prev[key] }));
    };

    const expandAll = () => {
        const next = {};
        Array.from(treeMap.values()).forEach((node) => {
            if (!node.isLeaf) next[node.key] = true;
        });
        setExpandedNodes(next);
    };

    const collapseAll = () => {
        const next = {};
        Array.from(treeMap.values()).forEach((node) => {
            if (!node.isLeaf) next[node.key] = false;
        });
        setExpandedNodes(next);
    };

    const visibleRows = React.useMemo(() => {
        const flattened = [];

        const appendNode = (node) => {
            flattened.push(node);
            if (node.isLeaf || !expandedNodes[node.key]) return;

            Array.from(node.children)
                .map((key) => treeMap.get(key))
                .filter(Boolean)
                .forEach(appendNode);
        };

        topLevelRows.forEach(appendNode);

        if (viewMode === 'summary') {
            return flattened.filter((node) => !node.isLeaf);
        }

        return flattened;
    }, [expandedNodes, topLevelRows, treeMap, viewMode]);

    return (
        <AppLayout>
            <Head title='Laporan Rugi Laba' />
            <div className='p-6'>
                <div className='mb-4'>
                    <h1 className='text-xl font-semibold text-gray-800 dark:text-gray-100'>Laporan Rugi Laba</h1>
                    <p className='text-sm text-gray-500 dark:text-gray-400'>Laporan Keuangan &gt; Rugi Laba</p>
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

                <div className='mt-4 rounded-lg border bg-white p-3 dark:border-gray-900 dark:bg-gray-950'>
                    <div className='flex flex-wrap items-center gap-2'>
                        <button type='button' onClick={expandAll} className='rounded border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-900'>Expand All</button>
                        <button type='button' onClick={collapseAll} className='rounded border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-900'>Collapse All</button>
                        <button type='button' onClick={() => setViewMode('detail')} className={`rounded px-3 py-1.5 text-xs font-semibold ${viewMode === 'detail' ? 'bg-blue-600 text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-900'}`}>Show Detail</button>
                        <button type='button' onClick={() => setViewMode('summary')} className={`rounded px-3 py-1.5 text-xs font-semibold ${viewMode === 'summary' ? 'bg-blue-600 text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-900'}`}>Show Summary</button>
                    </div>
                </div>

                <div className='mt-4 overflow-hidden rounded-lg border bg-white dark:border-gray-900 dark:bg-gray-950'>
                    <div className='max-h-[65vh] overflow-auto'>
                        <Table className='overflow-visible rounded-none border-0'>
                            <Table.Thead>
                                <tr>
                                    <Table.Th className='sticky top-0 z-30 bg-gray-50 dark:bg-gray-950'>COA</Table.Th>
                                    <Table.Th className='sticky top-0 z-30 bg-gray-50 text-right dark:bg-gray-950'>Current Month ({selectedMonthLabel} {listFilters.year})</Table.Th>
                                    <Table.Th className='sticky top-0 z-30 bg-gray-50 text-right dark:bg-gray-950'>% Total Sales</Table.Th>
                                    <Table.Th className='sticky top-0 z-30 bg-gray-50 text-right dark:bg-gray-950'>Year to Date (Jan-{selectedMonthLabel} {listFilters.year})</Table.Th>
                                    <Table.Th className='sticky top-0 z-30 bg-gray-50 text-right dark:bg-gray-950'>% Total Sales</Table.Th>
                                    <Table.Th className='sticky top-0 z-30 bg-gray-50 text-right dark:bg-gray-950'>Last Year to Date (Jan-{selectedMonthLabel} {Number(listFilters.year) - 1})</Table.Th>
                                    <Table.Th className='sticky top-0 z-30 bg-gray-50 text-right dark:bg-gray-950'>% Total Sales</Table.Th>
                                </tr>
                            </Table.Thead>
                            <Table.Tbody>
                                {visibleRows.length > 0 ? visibleRows.map((node) => {
                                    const isParent = !node.isLeaf;
                                    const hasChildren = node.children.size > 0;
                                    const leftPadding = 12 + ((node.level - 1) * 20);
                                    const rowTone = isParent ? getParentTone(node.label) : 'bg-white text-gray-800 dark:bg-gray-950 dark:text-gray-100';
                                    const label = node.isLeaf && node.coa_code ? `${node.coa_code} - ${node.label}` : node.label;

                                    return (
                                        <tr key={node.key} className={rowTone}>
                                            <Table.Td>
                                                <div className='flex items-center gap-2' style={{ paddingLeft: `${leftPadding}px` }}>
                                                    {isParent && hasChildren ? (
                                                        <button type='button' onClick={() => toggleNode(node.key)} className='rounded p-0.5 text-gray-600 hover:bg-gray-200/70 dark:text-gray-300 dark:hover:bg-gray-800/70'>
                                                            {expandedNodes[node.key] ? <IconChevronDown size={14} /> : <IconChevronRight size={14} />}
                                                        </button>
                                                    ) : (
                                                        <span className='inline-block w-[18px]' />
                                                    )}
                                                    <span className={isParent ? 'font-semibold' : 'font-normal'}>{label}</span>
                                                </div>
                                            </Table.Td>
                                            <Table.Td className={getAmountClass(node.current_month, isParent)}>
                                                {node.isLeaf && node.coa_id ? <a href={getGeneralLedgerLink(listFilters, node.coa_id)} className='hover:underline'>{formatAmount(node.current_month)}</a> : formatAmount(node.current_month)}
                                            </Table.Td>
                                            <Table.Td className={`text-right ${isParent ? 'font-semibold' : ''}`}>{formatPercent(node.current_month_percent_sales)}</Table.Td>
                                            <Table.Td className={getAmountClass(node.year_to_date, isParent)}>
                                                {node.isLeaf && node.coa_id ? <a href={getGeneralLedgerLink(listFilters, node.coa_id)} className='hover:underline'>{formatAmount(node.year_to_date)}</a> : formatAmount(node.year_to_date)}
                                            </Table.Td>
                                            <Table.Td className={`text-right ${isParent ? 'font-semibold' : ''}`}>{formatPercent(node.year_to_date_percent_sales)}</Table.Td>
                                            <Table.Td className={getAmountClass(node.last_year_to_date, isParent)}>
                                                {node.isLeaf && node.coa_id ? <a href={getGeneralLedgerLink(listFilters, node.coa_id)} className='hover:underline'>{formatAmount(node.last_year_to_date)}</a> : formatAmount(node.last_year_to_date)}
                                            </Table.Td>
                                            <Table.Td className={`text-right ${isParent ? 'font-semibold' : ''}`}>{formatPercent(node.last_year_to_date_percent_sales)}</Table.Td>
                                        </tr>
                                    );
                                }) : (
                                    <Table.Empty colSpan={7} message={
                                        <div className='flex flex-col items-center gap-1 text-sm text-gray-500 dark:text-gray-300'>
                                            <IconDatabaseOff size={24} />
                                            <span>Data Laporan Rugi Laba tidak ditemukan.</span>
                                        </div>
                                    } />
                                )}
                                {rows.length > 0 && (
                                    <>
                                        <tr className='bg-gray-100/80 dark:bg-gray-900/70'>
                                            <Table.Td colSpan={7} className='sticky bottom-[52px] z-20 py-2 text-sm font-semibold text-gray-700 dark:text-gray-200'>
                                                Net Profit (Loss) = Total Revenue - Total Expenses
                                            </Table.Td>
                                        </tr>
                                        <tr className='bg-emerald-50/70 text-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-100'>
                                            <Table.Td className='sticky bottom-0 z-20 bg-emerald-50/95 dark:bg-emerald-950/95'>
                                                <div className='flex items-center gap-2'>
                                                    <span className='inline-block h-2 w-2 rounded-full bg-current opacity-60' />
                                                    <span className='font-semibold'>Total Net Profit (Loss)</span>
                                                </div>
                                            </Table.Td>
                                            <Table.Td className={`sticky bottom-0 z-20 bg-emerald-50/95 dark:bg-emerald-950/95 ${getAmountClass(summary?.net_profit_current_month, true)}`}>{formatAmount(summary?.net_profit_current_month)}</Table.Td>
                                            <Table.Td className='sticky bottom-0 z-20 bg-emerald-50/95 text-right font-semibold dark:bg-emerald-950/95'>{formatPercent(summary?.net_profit_margin_current_month)}</Table.Td>
                                            <Table.Td className={`sticky bottom-0 z-20 bg-emerald-50/95 dark:bg-emerald-950/95 ${getAmountClass(summary?.net_profit_year_to_date, true)}`}>{formatAmount(summary?.net_profit_year_to_date)}</Table.Td>
                                            <Table.Td className='sticky bottom-0 z-20 bg-emerald-50/95 text-right font-semibold dark:bg-emerald-950/95'>{formatPercent(summary?.net_profit_margin_year_to_date)}</Table.Td>
                                            <Table.Td className={`sticky bottom-0 z-20 bg-emerald-50/95 dark:bg-emerald-950/95 ${getAmountClass(summary?.net_profit_last_year_to_date, true)}`}>{formatAmount(summary?.net_profit_last_year_to_date)}</Table.Td>
                                            <Table.Td className='sticky bottom-0 z-20 bg-emerald-50/95 text-right font-semibold dark:bg-emerald-950/95'>{formatPercent(summary?.net_profit_margin_last_year_to_date)}</Table.Td>
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
