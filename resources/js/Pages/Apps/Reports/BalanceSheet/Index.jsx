import AppLayout from '@/Layouts/AppLayout';
import { Head, router, usePage } from '@inertiajs/react';
import React from 'react';
import Table from '@/Components/Table';
import { IconDatabaseOff } from '@tabler/icons-react';

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

const getDisplayLabel = (row, drillLevel) => {
    if (drillLevel >= 4) return row.coa_level_4 || row.coa_level_3 || row.coa_level_2 || row.coa_level_1 || '-';
    if (drillLevel === 3) return row.coa_level_3 || row.coa_level_2 || row.coa_level_1 || '-';
    if (drillLevel === 2) return row.coa_level_2 || row.coa_level_1 || '-';

    return row.coa_level_1 || '-';
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
        drill_level: Number(filters?.drill_level ?? 1),
    });

    const applyFilters = React.useCallback((nextFilters) => {
        router.get(route('apps.reports.balance-sheet'), nextFilters, {
            preserveState: true,
            replace: true,
        });
    }, []);

    const updateFilter = (field, value) => {
        const nextFilters = { ...listFilters, [field]: value };
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
                                {rows.length > 0 ? rows.map((row, index) => (
                                    <tr key={`${row.segment_key}-${row.coa_code ?? row.coa_level_1}-${index}`} className={getRowTone(row)}>
                                        <Table.Td className='!py-3 font-semibold'>{row.segment}</Table.Td>
                                        <Table.Td className='!py-3 !pl-2'>
                                            <span className='font-medium'>{getDisplayLabel(row, listFilters.drill_level)}</span>
                                        </Table.Td>
                                        <Table.Td className={`!py-3 ${getAmountClass(row.current_year)}`}>{formatAmount(row.current_year)}</Table.Td>
                                        <Table.Td className='!py-3 text-right'>{formatPercent(row.current_year_percent_asset)}</Table.Td>
                                        <Table.Td className={`!py-3 ${getAmountClass(row.current_year)}`}>{formatAmount(row.current_year)}</Table.Td>
                                        <Table.Td className='!py-3 text-right'>{formatPercent(row.current_year_percent_asset)}</Table.Td>
                                        <Table.Td className={`!py-3 ${getAmountClass(row.previous_year)}`}>{formatAmount(row.previous_year)}</Table.Td>
                                        <Table.Td className='!py-3 text-right'>{formatPercent(row.previous_year_percent_asset)}</Table.Td>
                                    </tr>
                                )) : (
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
