import AppLayout from '@/Layouts/AppLayout';
import { Head, router, usePage } from '@inertiajs/react';
import React from 'react';
import Table from '@/Components/Table';
import Pagination from '@/Components/Pagination';
import { IconArrowsSort, IconDatabaseOff, IconSearch } from '@tabler/icons-react';

const formatAmount = (value) => new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
}).format(Number(value || 0));

const formatQuantity = (value) => {
    if (value === null || value === undefined || value === '') return '-';

    return new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 4,
    }).format(Number(value || 0));
};

const formatDate = (value) => {
    if (!value) return '-';
    const d = new Date(`${value}T12:00:00Z`);
    if (Number.isNaN(d.getTime())) return '-';

    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(d);
};

const buildManualJournalEditUrl = (line) => {
    if (!line?.journal_entry_id || line?.journal_type !== 'manual') {
        return null;
    }

    const postingDate = typeof line.date === 'string' ? line.date : '';
    const [yearPart, monthPart] = postingDate.split('-');
    const year = Number(yearPart);
    const month = Number(monthPart);

    return route('apps.manual-journals.index', {
        year: Number.isFinite(year) && year > 0 ? year : new Date().getUTCFullYear(),
        month: Number.isFinite(month) && month >= 1 && month <= 12 ? month : 'all',
        status: 'all',
        edit_journal_id: line.journal_entry_id,
    });
};

export default function Index() {
    const { ledgerLines, summary, companies, branches, accounts, statusOptions, filters, sort, yearOptions = [] } = usePage().props;

    const [listFilters, setListFilters] = React.useState({
        year: `${filters?.year ?? new Date().getUTCFullYear()}`,
        date_from: filters?.date_from ?? '',
        date_to: filters?.date_to ?? '',
        company_id: `${filters?.company_id ?? 'all'}`,
        branch_id: `${filters?.branch_id ?? 'all'}`,
        coa_id: `${filters?.coa_id ?? ''}`,
        status: filters?.status ?? 'posted',
        search: filters?.search ?? '',
    });

    const currentSortBy = sort?.by ?? 'date';
    const currentSortDirection = sort?.direction ?? 'asc';

    const yearMin = `${listFilters.year}-01-01`;
    const yearMax = `${listFilters.year}-12-31`;

    const applyFilters = React.useCallback((nextFilters, sortBy = currentSortBy, sortDirection = currentSortDirection) => {
        router.get(route('apps.reports.general-ledger'), {
            ...nextFilters,
            sort_by: sortBy,
            sort_direction: sortDirection,
        }, {
            preserveState: true,
            replace: true,
        });
    }, [currentSortBy, currentSortDirection]);

    const normalizeRangeByYear = (nextFilters) => {
        const normalized = { ...nextFilters };
        const selectedYear = Number.parseInt(`${normalized.year}`, 10);
        const safeYear = Number.isFinite(selectedYear) && selectedYear > 0 ? selectedYear : new Date().getUTCFullYear();
        const rangeStart = `${safeYear}-01-01`;
        const rangeEnd = `${safeYear}-12-31`;

        if (normalized.date_from && normalized.date_from < rangeStart) normalized.date_from = rangeStart;
        if (normalized.date_from && normalized.date_from > rangeEnd) normalized.date_from = rangeEnd;
        if (normalized.date_to && normalized.date_to < rangeStart) normalized.date_to = rangeStart;
        if (normalized.date_to && normalized.date_to > rangeEnd) normalized.date_to = rangeEnd;
        if (normalized.date_from && normalized.date_to && normalized.date_to < normalized.date_from) {
            normalized.date_to = normalized.date_from;
        }

        return normalized;
    };

    const updateFilter = (field, value) => {
        let nextFilters = { ...listFilters, [field]: value };

        if (field === 'company_id' && value !== listFilters.company_id) {
            const filteredCoaOptions = accounts.filter((account) => value === 'all' || Number(account.company_id) === Number(value));
            nextFilters.coa_id = filteredCoaOptions[0] ? `${filteredCoaOptions[0].id}` : '';
        }

        nextFilters = normalizeRangeByYear(nextFilters);
        setListFilters(nextFilters);

        if (field !== 'search') {
            applyFilters(nextFilters);
        }
    };

    const submitSearch = (event) => {
        event.preventDefault();
        applyFilters(normalizeRangeByYear(listFilters));
    };

    const toggleSort = (field) => {
        const nextDirection = currentSortBy === field && currentSortDirection === 'asc' ? 'desc' : 'asc';
        applyFilters(normalizeRangeByYear(listFilters), field, nextDirection);
    };

    const branchOptions = branches.filter((branch) => listFilters.company_id === 'all' || Number(branch.company_id) === Number(listFilters.company_id));
    const coaOptions = accounts.filter((account) => listFilters.company_id === 'all' || Number(account.company_id) === Number(listFilters.company_id));

    const SortHeader = ({ field, label, className = '' }) => (
        <button
            type='button'
            onClick={() => toggleSort(field)}
            className={`inline-flex items-center gap-1 hover:text-blue-600 dark:hover:text-blue-400 ${className}`}
        >
            {label}
            <IconArrowsSort size={14} />
        </button>
    );

    return (
        <AppLayout>
            <Head title='General Ledger Report' />
            <div className='p-6'>
                <div className='mb-4'>
                    <h1 className='text-xl font-semibold text-gray-800 dark:text-gray-100'>General Ledger Report</h1>
                    <p className='text-sm text-gray-500 dark:text-gray-400'>Laporan Keuangan &gt; General Ledger</p>
                </div>

                <div className='rounded-lg border bg-white p-4 dark:border-gray-900 dark:bg-gray-950'>
                    <div className='grid grid-cols-1 gap-3 md:grid-cols-8'>
                        <div>
                            <label className='mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300'>Year</label>
                            <select className='w-full rounded border-gray-300 bg-white text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100' value={listFilters.year} onChange={(e) => updateFilter('year', e.target.value)}>
                                {yearOptions.map((year) => <option key={year} value={year}>{year}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className='mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300'>Date From</label>
                            <input type='date' min={yearMin} max={yearMax} className='w-full rounded border-gray-300 bg-white text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100' value={listFilters.date_from} onChange={(e) => updateFilter('date_from', e.target.value)} />
                        </div>
                        <div>
                            <label className='mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300'>To Date</label>
                            <input type='date' min={yearMin} max={yearMax} className='w-full rounded border-gray-300 bg-white text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100' value={listFilters.date_to} onChange={(e) => updateFilter('date_to', e.target.value)} />
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
                            <label className='mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300'>COA</label>
                            <select className='w-full rounded border-gray-300 bg-white text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100' value={listFilters.coa_id} onChange={(e) => updateFilter('coa_id', e.target.value)}>
                                {coaOptions.map((account) => <option key={account.id} value={account.id}>{account.code} - {account.name}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className='mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300'>Status</label>
                            <select className='w-full rounded border-gray-300 bg-white text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100' value={listFilters.status} onChange={(e) => updateFilter('status', e.target.value)}>
                                {statusOptions.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className='mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300'>Global Search</label>
                            <form onSubmit={submitSearch} className='relative'>
                                <input type='text' className='w-full rounded border-gray-300 bg-white py-2 pl-8 pr-2 text-sm text-gray-700 placeholder:text-gray-400 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 dark:placeholder:text-gray-500' placeholder='Cari dokumen/source/lawan/COA/barang...' value={listFilters.search} onChange={(e) => updateFilter('search', e.target.value)} />
                                <IconSearch size={14} className='absolute left-2 top-2.5 text-gray-400 dark:text-gray-500' />
                            </form>
                        </div>
                    </div>
                </div>

                <div className='mt-4 overflow-x-auto rounded-lg border bg-white dark:border-gray-900 dark:bg-gray-950'>
                    <Table>
                        <Table.Thead>
                            <tr>
                                <Table.Th className='text-right'>Saldo Awal</Table.Th>
                                <Table.Th className='text-right'>Total Debet</Table.Th>
                                <Table.Th className='text-right'>Total Kredit</Table.Th>
                                <Table.Th className='text-right'>Saldo Akhir</Table.Th>
                            </tr>
                        </Table.Thead>
                        <Table.Tbody>
                            <tr>
                                <Table.Td className='text-right font-medium'>{formatAmount(summary?.opening_balance)}</Table.Td>
                                <Table.Td className='text-right font-medium'>{formatAmount(summary?.total_debit)}</Table.Td>
                                <Table.Td className='text-right font-medium'>{formatAmount(summary?.total_credit)}</Table.Td>
                                <Table.Td className='text-right font-semibold'>{formatAmount(summary?.closing_balance)}</Table.Td>
                            </tr>
                        </Table.Tbody>
                    </Table>
                </div>

                <div className='mt-4 overflow-x-auto rounded-lg border bg-white dark:border-gray-900 dark:bg-gray-950'>
                    <Table>
                        <Table.Thead>
                            <tr>
                                <Table.Th><SortHeader field='no' label='No' /></Table.Th>
                                <Table.Th><SortHeader field='date' label='Tanggal' /></Table.Th>
                                <Table.Th><SortHeader field='document_no' label='No Dokumen' /></Table.Th>
                                <Table.Th><SortHeader field='salesperson_code' label='Kode Salesman' /></Table.Th>
                                <Table.Th><SortHeader field='salesperson_name' label='Nama Salesman' /></Table.Th>
                                <Table.Th><SortHeader field='counterparty_type' label='Tipe Lawan' /></Table.Th>
                                <Table.Th><SortHeader field='counterparty_code' label='Kode Lawan' /></Table.Th>
                                <Table.Th><SortHeader field='counterparty_name' label='Nama Lawan' /></Table.Th>
                                <Table.Th><SortHeader field='reference' label='Referensi' /></Table.Th>
                                <Table.Th><SortHeader field='source_module' label='Kode Transaksi' /></Table.Th>
                                <Table.Th><SortHeader field='source_module_name' label='Nama Transaksi' /></Table.Th>
                                <Table.Th><SortHeader field='coa_code' label='Kode COA' /></Table.Th>
                                <Table.Th><SortHeader field='coa_name' label='Nama COA' /></Table.Th>
                                <Table.Th className='text-right'><SortHeader field='debit' label='Debet' className='w-full justify-end' /></Table.Th>
                                <Table.Th className='text-right'><SortHeader field='credit' label='Kredit' className='w-full justify-end' /></Table.Th>
                                <Table.Th><SortHeader field='detail_description' label='Keterangan' /></Table.Th>
                                <Table.Th><SortHeader field='cost_center_code' label='Kode Cost Center' /></Table.Th>
                                <Table.Th><SortHeader field='cost_center_name' label='Nama Cost Center' /></Table.Th>
                                <Table.Th><SortHeader field='item_code' label='Kode Barang' /></Table.Th>
                                <Table.Th><SortHeader field='item_name' label='Nama Barang' /></Table.Th>
                                <Table.Th className='text-right'><SortHeader field='quantity' label='Qty' className='w-full justify-end' /></Table.Th>
                                <Table.Th><SortHeader field='quantity_uom' label='UOM' /></Table.Th>
                            </tr>
                        </Table.Thead>
                        <Table.Tbody>
                            {ledgerLines.data.length > 0 ? ledgerLines.data.map((line) => {
                                const editUrl = buildManualJournalEditUrl(line);

                                return (
                                    <tr key={`${line.journal_no}-${line.no}`}>
                                    <Table.Td>{line.no}</Table.Td>
                                    <Table.Td>{formatDate(line.date)}</Table.Td>
                                    <Table.Td>
                                        {editUrl ? (
                                            <a href={editUrl} className='text-blue-600 hover:underline dark:text-blue-400'>
                                                {line.document_no || '-'}
                                            </a>
                                        ) : (line.document_no || '-')}
                                    </Table.Td>
                                    <Table.Td>{line.salesperson_code || '-'}</Table.Td>
                                    <Table.Td>{line.salesperson_name || '-'}</Table.Td>
                                    <Table.Td>{line.counterparty_type || '-'}</Table.Td>
                                    <Table.Td>{line.counterparty_code || '-'}</Table.Td>
                                    <Table.Td>{line.counterparty_name || '-'}</Table.Td>
                                    <Table.Td>{line.reference || '-'}</Table.Td>
                                    <Table.Td>{line.source_module || '-'}</Table.Td>
                                    <Table.Td>{line.source_module_name || '-'}</Table.Td>
                                    <Table.Td>{line.coa_code || '-'}</Table.Td>
                                    <Table.Td>{line.coa_name || '-'}</Table.Td>
                                    <Table.Td className='text-right'>{formatAmount(line.debit)}</Table.Td>
                                    <Table.Td className='text-right'>{formatAmount(line.credit)}</Table.Td>
                                    <Table.Td className='max-w-56 truncate'>{line.description || '-'}</Table.Td>
                                    <Table.Td>{line.cost_center_code || '-'}</Table.Td>
                                    <Table.Td>{line.cost_center_name || '-'}</Table.Td>
                                    <Table.Td>{line.item_code || '-'}</Table.Td>
                                    <Table.Td>{line.item_name || '-'}</Table.Td>
                                    <Table.Td className='text-right'>{formatQuantity(line.quantity)}</Table.Td>
                                    <Table.Td>{line.quantity_uom || '-'}</Table.Td>
                                </tr>
                                );
                            }) : (
                                <Table.Empty colSpan={22} message={
                                    <div className='flex flex-col items-center gap-1 text-sm text-gray-500 dark:text-gray-300'>
                                        <IconDatabaseOff size={24} />
                                        <span>Data General Ledger tidak ditemukan.</span>
                                    </div>
                                } />
                            )}
                        </Table.Tbody>
                    </Table>

                    <div className='border-t p-3 dark:border-gray-900'>
                        <Pagination links={ledgerLines.links} align='end' />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
