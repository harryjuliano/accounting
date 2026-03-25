import AppLayout from '@/Layouts/AppLayout';
import { Head, router, usePage } from '@inertiajs/react';
import React from 'react';

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

const formatAmount = (value) => new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
}).format(Math.abs(Number(value || 0)));

const printAmount = (value) => (Number(value || 0) < 0 ? `(${formatAmount(value)})` : formatAmount(value));

const formatPercent = (value) => `${new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
}).format(Number(value || 0))}%`;

export default function Index() {
    const { companies, branches, statusOptions, filters, report, yearOptions = [] } = usePage().props;
    const now = new Date();
    const fallbackYear = Number(filters?.year ?? now.getFullYear());
    const resolvedYear = yearOptions.includes(fallbackYear)
        ? fallbackYear
        : (yearOptions[0] ?? fallbackYear);

    const [listFilters, setListFilters] = React.useState({
        company_id: `${filters?.company_id ?? 'all'}`,
        branch_id: `${filters?.branch_id ?? 'all'}`,
        status: filters?.status ?? 'posted',
        year: resolvedYear,
        period: Number(filters?.period ?? now.getMonth() + 1),
    });

    const applyFilters = React.useCallback((nextFilters) => {
        router.get(route('apps.reports.indirect-cash-flow'), nextFilters, {
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
            ...Object.entries(listFilters).reduce((carry, [key, value]) => ({ ...carry, [key]: `${value}` }), {}),
            export: 'pdf',
        });

        window.open(`${route('apps.reports.indirect-cash-flow')}?${query.toString()}`, '_blank');
    };

    const rows = report?.rows || [];
    const salesCurrent = Number(report?.netSales?.current || 0);
    const salesPrevious = Number(report?.netSales?.previous || 0);
    const salesVariance = salesCurrent - salesPrevious;
    const branchOptions = branches.filter((branch) => listFilters.company_id === 'all' || Number(branch.company_id) === Number(listFilters.company_id));

    return (
        <AppLayout>
            <Head title='Laporan Arus Kas Tidak Langsung' />
            <div className='p-6'>
                <h1 className='text-xl font-semibold text-gray-800 dark:text-gray-100'>Laporan Arus Kas Tidak Langsung</h1>
                <p className='mb-4 text-sm text-gray-500 dark:text-gray-400'>Laporan Keuangan &gt; Arus Kas Tidak Langsung</p>

                <div className='rounded-lg border bg-white p-4 dark:border-gray-900 dark:bg-gray-950'>
                    <div className='grid grid-cols-1 gap-3 md:grid-cols-6'>
                        <select className='rounded border-gray-300 bg-white text-sm dark:border-gray-700 dark:bg-gray-900' value={listFilters.company_id} onChange={(e) => updateFilter('company_id', e.target.value)}>
                            <option value='all'>All Company</option>
                            {companies.map((company) => <option key={company.id} value={company.id}>{company.name}</option>)}
                        </select>
                        <select className='rounded border-gray-300 bg-white text-sm dark:border-gray-700 dark:bg-gray-900' value={listFilters.branch_id} onChange={(e) => updateFilter('branch_id', e.target.value)}>
                            <option value='all'>All Branch</option>
                            {branchOptions.map((branch) => <option key={branch.id} value={branch.id}>{branch.code} - {branch.name}</option>)}
                        </select>
                        <select className='rounded border-gray-300 bg-white text-sm dark:border-gray-700 dark:bg-gray-900' value={listFilters.status} onChange={(e) => updateFilter('status', e.target.value)}>
                            {statusOptions.map((status) => <option key={status.value} value={status.value}>{status.label}</option>)}
                        </select>
                        <select className='rounded border-gray-300 bg-white text-sm dark:border-gray-700 dark:bg-gray-900' value={listFilters.year} onChange={(e) => updateFilter('year', Number(e.target.value))}>
                            {yearOptions.map((year) => <option key={year} value={year}>{year}</option>)}
                        </select>
                        <select className='rounded border-gray-300 bg-white text-sm dark:border-gray-700 dark:bg-gray-900' value={listFilters.period} onChange={(e) => updateFilter('period', Number(e.target.value))}>
                            {monthOptions.map((month) => <option key={month.value} value={month.value}>{month.label}</option>)}
                        </select>
                        <button type='button' onClick={exportPdf} className='rounded bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700'>Export PDF</button>
                    </div>
                </div>

                <div className='mt-4 overflow-auto rounded-lg border bg-white p-4 text-sm dark:border-gray-900 dark:bg-gray-950'>
                    <table className='min-w-full border-collapse'>
                        <thead>
                            <tr className='border-b text-left'>
                                <th className='py-2'>Uraian</th><th className='py-2 text-right'>{listFilters.year}</th><th className='py-2 text-right'>{listFilters.year - 1}</th><th className='py-2 text-right'>Variance</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row, idx) => {
                                const variance = Number(row.current || 0) - Number(row.previous || 0);
                                const currPct = salesCurrent ? (Number(row.current || 0) / salesCurrent) * 100 : 0;
                                const prevPct = salesPrevious ? (Number(row.previous || 0) / salesPrevious) * 100 : 0;
                                const varPct = salesVariance ? (variance / salesVariance) * 100 : 0;
                                return (
                                    <tr key={`${row.label}-${idx}`} className='border-b'>
                                        <td className='py-2'>{row.label}</td>
                                        <td className='py-2 text-right'>{printAmount(row.current)} <span className='text-xs text-gray-400'>({formatPercent(currPct)})</span></td>
                                        <td className='py-2 text-right'>{printAmount(row.previous)} <span className='text-xs text-gray-400'>({formatPercent(prevPct)})</span></td>
                                        <td className='py-2 text-right'>{printAmount(variance)} <span className='text-xs text-gray-400'>({formatPercent(varPct)})</span></td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
