import AppLayout from '@/Layouts/AppLayout';
import { Head, router, usePage } from '@inertiajs/react';
import React from 'react';

const monthOptions = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

const formatAmount = (value) => new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
}).format(Math.abs(Number(value || 0)));

const printAmount = (value) => (Number(value || 0) < 0 ? `(${formatAmount(value)})` : formatAmount(value));

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
    const months = report?.months || monthOptions.map((label, index) => ({ value: index + 1, label }));
    const branchOptions = branches.filter((branch) => listFilters.company_id === 'all' || Number(branch.company_id) === Number(listFilters.company_id));

    return (
        <AppLayout>
            <Head title='Laporan Arus Kas Tidak Langsung' />
            <div className='p-6'>
                <h1 className='text-xl font-semibold text-gray-800 dark:text-gray-100'>Laporan Arus Kas Tidak Langsung</h1>
                <p className='mb-4 text-sm text-gray-500 dark:text-gray-400'>Laporan Keuangan &gt; Arus Kas Tidak Langsung</p>

                <div className='rounded-lg border bg-white p-4 text-gray-800 dark:border-gray-900 dark:bg-gray-950 dark:text-gray-100'>
                    <div className='grid grid-cols-1 gap-3 md:grid-cols-5'>
                        <select className='rounded border-gray-300 bg-white text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100' value={listFilters.company_id} onChange={(e) => updateFilter('company_id', e.target.value)}>
                            <option value='all'>All Company</option>
                            {companies.map((company) => <option key={company.id} value={company.id}>{company.name}</option>)}
                        </select>
                        <select className='rounded border-gray-300 bg-white text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100' value={listFilters.branch_id} onChange={(e) => updateFilter('branch_id', e.target.value)}>
                            <option value='all'>All Branch</option>
                            {branchOptions.map((branch) => <option key={branch.id} value={branch.id}>{branch.code} - {branch.name}</option>)}
                        </select>
                        <select className='rounded border-gray-300 bg-white text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100' value={listFilters.status} onChange={(e) => updateFilter('status', e.target.value)}>
                            {statusOptions.map((status) => <option key={status.value} value={status.value}>{status.label}</option>)}
                        </select>
                        <select className='rounded border-gray-300 bg-white text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100' value={listFilters.year} onChange={(e) => updateFilter('year', Number(e.target.value))}>
                            {yearOptions.map((year) => <option key={year} value={year}>{year}</option>)}
                        </select>
                        <button type='button' onClick={exportPdf} className='rounded bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700'>Export PDF</button>
                    </div>
                </div>

                <div className='mt-4 overflow-auto rounded-lg border bg-white p-4 text-sm text-gray-800 dark:border-gray-900 dark:bg-gray-950 dark:text-gray-100'>
                    <table className='min-w-full border-collapse'>
                        <thead>
                            <tr className='border-b border-gray-200 text-left dark:border-gray-700'>
                                <th className='py-2'>Uraian</th>
                                {months.map((month) => (
                                    <th key={month.value} className='py-2 text-right'>{month.label}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row, idx) => {
                                return (
                                    <tr key={`${row.label}-${idx}`} className='border-b border-gray-200 dark:border-gray-800'>
                                        <td className='py-2'>{row.label}</td>
                                        {(row.values || []).map((value, monthIndex) => (
                                            <td key={`${row.label}-${monthIndex}`} className='py-2 text-right'>{printAmount(value)}</td>
                                        ))}
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
