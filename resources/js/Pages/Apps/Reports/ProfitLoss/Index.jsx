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
    const label = `${row.coa_level_1 ?? ''} ${row.coa_level_2 ?? ''} ${row.coa_level_3 ?? ''} ${row.coa_level_4 ?? ''}`.toLowerCase();

    if (label.includes('pendapatan') || label.includes('revenue')) {
        return 'bg-blue-50/70 text-blue-900 dark:bg-blue-950/30 dark:text-blue-100';
    }

    if (label.includes('beban') || label.includes('expense') || label.includes('harga pokok') || label.includes('cogs')) {
        return 'bg-rose-50/70 text-rose-900 dark:bg-rose-950/30 dark:text-rose-100';
    }

    return 'bg-white text-gray-800 dark:bg-gray-950 dark:text-gray-100';
};

const getAmountClass = (value) => Number(value || 0) < 0
    ? 'text-right font-medium text-rose-600 dark:text-rose-300'
    : 'text-right font-medium text-gray-800 dark:text-gray-100';

const hasDeeperLevel = (row, drillLevel) => {
    if (drillLevel >= 4) return false;

    const nextLevelKey = `coa_level_${drillLevel + 1}`;
    return Boolean(row?.[nextLevelKey]);
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
        type: filters?.type ?? 'MTD',
        company_id: `${filters?.company_id ?? 'all'}`,
        branch_id: `${filters?.branch_id ?? 'all'}`,
        status: filters?.status ?? 'posted',
        year: resolvedYear,
        period: fallbackPeriod,
        drill_level: Number(filters?.drill_level ?? 4),
    });

    const applyFilters = React.useCallback((nextFilters) => {
        router.get(route('apps.reports.profit-loss'), nextFilters, {
            preserveState: true,
            replace: true,
        });
    }, []);

    const updateFilter = (field, value) => {
        const nextFilters = { ...listFilters, [field]: value };
        setListFilters(nextFilters);
        applyFilters(nextFilters);
    };

    const branchOptions = branches.filter((branch) => listFilters.company_id === 'all' || Number(branch.company_id) === Number(listFilters.company_id));

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
                            <label className='mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300'>Type</label>
                            <select className='w-full rounded border-gray-300 bg-white text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100' value={listFilters.type} onChange={(e) => updateFilter('type', e.target.value)}>
                                <option value='MTD'>MTD</option>
                                <option value='YTD'>YTD</option>
                            </select>
                        </div>
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
                            <span className='inline-flex rounded border border-gray-200 bg-gray-50 px-3 py-2 text-xs font-medium text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300'>
                                Drill COA Level {listFilters.drill_level}
                            </span>
                        </div>
                    </div>
                </div>

                <div className='mt-4 overflow-hidden rounded-lg border bg-white dark:border-gray-900 dark:bg-gray-950'>
                    <Table>
                        <Table.Thead>
                            <tr>
                                <Table.Th>COA</Table.Th>
                                <Table.Th className='text-right'>Current Year</Table.Th>
                                <Table.Th className='text-right'>% Total Sales</Table.Th>
                                <Table.Th className='text-right'>Tahun Sebelumnya</Table.Th>
                                <Table.Th className='text-right'>% Total Sales</Table.Th>
                                <Table.Th className='text-right'>Variance</Table.Th>
                                <Table.Th className='text-right'>% Total Sales</Table.Th>
                            </tr>
                        </Table.Thead>
                        <Table.Tbody>
                            {rows.length > 0 ? rows.map((row, index) => (
                                <tr key={`${row.coa_code ?? row.coa_level_1}-${index}`} className={getRowTone(row)}>
                                    <Table.Td>
                                        <div className='flex items-center gap-2'>
                                            <button
                                                type='button'
                                                className='inline-flex h-5 w-5 items-center justify-center rounded border border-gray-300 bg-white text-xs font-semibold text-gray-700 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100'
                                                onClick={() => updateFilter('drill_level', listFilters.drill_level - 1)}
                                                disabled={listFilters.drill_level <= 1}
                                                title='Drill up'
                                            >
                                                -
                                            </button>
                                            <button
                                                type='button'
                                                className='inline-flex h-5 w-5 items-center justify-center rounded border border-gray-300 bg-white text-xs font-semibold text-gray-700 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100'
                                                onClick={() => updateFilter('drill_level', listFilters.drill_level + 1)}
                                                disabled={!hasDeeperLevel(row, listFilters.drill_level)}
                                                title='Drill down'
                                            >
                                                +
                                            </button>
                                            <span className='font-medium'>{getDisplayLabel(row, listFilters.drill_level)}</span>
                                        </div>
                                    </Table.Td>
                                    <Table.Td className={getAmountClass(row.current_year)}>{formatAmount(row.current_year)}</Table.Td>
                                    <Table.Td className='text-right'>{formatPercent(row.current_year_percent_sales)}</Table.Td>
                                    <Table.Td className={getAmountClass(row.previous_year)}>{formatAmount(row.previous_year)}</Table.Td>
                                    <Table.Td className='text-right'>{formatPercent(row.previous_year_percent_sales)}</Table.Td>
                                    <Table.Td className={getAmountClass(row.variance)}>{formatAmount(row.variance)}</Table.Td>
                                    <Table.Td className='text-right'>{formatPercent(row.variance_percent_sales)}</Table.Td>
                                </tr>
                            )) : (
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
                                        <Table.Td colSpan={7} className='py-2 text-sm font-semibold text-gray-700 dark:text-gray-200'>
                                            Net Profit (Loss) = Total Revenue - Total Expenses
                                        </Table.Td>
                                    </tr>
                                    <tr className='bg-emerald-50/70 text-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-100'>
                                        <Table.Td>
                                            <div className='flex items-center gap-2'>
                                                <span className='inline-block h-2 w-2 rounded-full bg-current opacity-60' />
                                                <span className='font-semibold'>Total Net Profit (Loss)</span>
                                            </div>
                                        </Table.Td>
                                        <Table.Td className={getAmountClass(summary?.net_profit_current_year)}>{formatAmount(summary?.net_profit_current_year)}</Table.Td>
                                        <Table.Td className='text-right font-semibold'>{formatPercent(summary?.net_profit_margin_current_year)}</Table.Td>
                                        <Table.Td className={getAmountClass(summary?.net_profit_previous_year)}>{formatAmount(summary?.net_profit_previous_year)}</Table.Td>
                                        <Table.Td className='text-right font-semibold'>{formatPercent(summary?.net_profit_margin_previous_year)}</Table.Td>
                                        <Table.Td className={getAmountClass(summary?.net_profit_variance)}>{formatAmount(summary?.net_profit_variance)}</Table.Td>
                                        <Table.Td className='text-right font-semibold'>{formatPercent(summary?.net_profit_margin_variance)}</Table.Td>
                                    </tr>
                                </>
                            )}
                        </Table.Tbody>
                    </Table>
                </div>
            </div>
        </AppLayout>
    );
}
