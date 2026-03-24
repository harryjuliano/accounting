import AppLayout from '@/Layouts/AppLayout';
import { Head, router, usePage } from '@inertiajs/react';
import React from 'react';
import Table from '@/Components/Table';
import { IconDatabaseOff } from '@tabler/icons-react';

const formatAmount = (value) => new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
}).format(Number(value || 0));

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

export default function Index() {
    const { rows, companies, branches, statusOptions, filters } = usePage().props;

    const [listFilters, setListFilters] = React.useState({
        type: filters?.type ?? 'MTD',
        company_id: `${filters?.company_id ?? 'all'}`,
        branch_id: `${filters?.branch_id ?? 'all'}`,
        status: filters?.status ?? 'posted',
        year: Number(filters?.year ?? new Date().getFullYear()),
        period: Number(filters?.period ?? (new Date().getMonth() + 1)),
    });

    const applyFilters = React.useCallback((nextFilters) => {
        router.get(route('apps.reports.trial-balance'), nextFilters, {
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
            <Head title='Trial Balance Report' />
            <div className='p-6'>
                <div className='mb-4'>
                    <h1 className='text-xl font-semibold text-gray-800 dark:text-gray-100'>Trial Balance Report</h1>
                    <p className='text-sm text-gray-500 dark:text-gray-400'>Laporan Keuangan &gt; Trial Balance</p>
                </div>

                <div className='rounded-lg border bg-white p-4 dark:border-gray-900 dark:bg-gray-950'>
                    <div className='grid grid-cols-1 gap-3 md:grid-cols-6'>
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
                            <input type='number' min='2000' max='2100' className='w-full rounded border-gray-300 bg-white text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100' value={listFilters.year} onChange={(e) => updateFilter('year', Number(e.target.value))} />
                        </div>
                        <div>
                            <label className='mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300'>Periode</label>
                            <select className='w-full rounded border-gray-300 bg-white text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100' value={listFilters.period} onChange={(e) => updateFilter('period', Number(e.target.value))}>
                                {monthOptions.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
                            </select>
                        </div>
                    </div>
                </div>

                <div className='mt-4 overflow-hidden rounded-lg border bg-white dark:border-gray-900 dark:bg-gray-950'>
                    <Table>
                        <Table.Thead>
                            <tr>
                                <Table.Th>COA Level 1</Table.Th>
                                <Table.Th>COA Level 2</Table.Th>
                                <Table.Th>COA Level 3</Table.Th>
                                <Table.Th>COA Level 4</Table.Th>
                                <Table.Th>Kode COA</Table.Th>
                                <Table.Th className='text-right'>Saldo Awal</Table.Th>
                                <Table.Th className='text-right'>Mutasi Debet</Table.Th>
                                <Table.Th className='text-right'>Mutasi Kredit</Table.Th>
                                <Table.Th className='text-right'>Saldo Akhir</Table.Th>
                            </tr>
                        </Table.Thead>
                        <Table.Tbody>
                            {rows.length > 0 ? rows.map((row, index) => (
                                <tr key={`${row.coa_code}-${index}`}>
                                    <Table.Td>{row.coa_level_1 || '-'}</Table.Td>
                                    <Table.Td>{row.coa_level_2 || '-'}</Table.Td>
                                    <Table.Td>{row.coa_level_3 || '-'}</Table.Td>
                                    <Table.Td>{row.coa_level_4 || '-'}</Table.Td>
                                    <Table.Td>{row.coa_code || '-'}</Table.Td>
                                    <Table.Td className='text-right'>{formatAmount(row.opening_balance)}</Table.Td>
                                    <Table.Td className='text-right'>{formatAmount(row.mutation_debit)}</Table.Td>
                                    <Table.Td className='text-right'>{formatAmount(row.mutation_credit)}</Table.Td>
                                    <Table.Td className='text-right font-medium'>{formatAmount(row.closing_balance)}</Table.Td>
                                </tr>
                            )) : (
                                <Table.Empty colSpan={9} message={
                                    <div className='flex flex-col items-center gap-1 text-sm text-gray-500 dark:text-gray-300'>
                                        <IconDatabaseOff size={24} />
                                        <span>Data Trial Balance tidak ditemukan.</span>
                                    </div>
                                } />
                            )}
                        </Table.Tbody>
                    </Table>
                </div>
            </div>
        </AppLayout>
    );
}
