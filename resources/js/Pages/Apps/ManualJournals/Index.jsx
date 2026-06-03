import AppLayout from '@/Layouts/AppLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import React from 'react';
import Button from '@/Components/Button';
import Modal from '@/Components/Modal';
import Input from '@/Components/Input';
import Table from '@/Components/Table';
import Pagination from '@/Components/Pagination';
import { IconAlertCircle, IconArrowsSort, IconCircleCheck, IconCirclePlus, IconDatabaseOff, IconFileImport, IconFileSpreadsheet, IconNotes, IconPencilCheck, IconPencilCog, IconPlus, IconSearch, IconTrash, IconX } from '@tabler/icons-react';

const emptyLine = { account_id: '', description: '', debit: 0, credit: 0, dimension_details: [] };
const getTodayDate = () => new Date().toISOString().split('T')[0];
const normalizeFormDate = (value, fallback = '') => {
    if (!value) {
        return fallback;
    }

    if (typeof value === 'string') {
        const trimmed = value.trim();

        if (/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) {
            return trimmed;
        }

        if (trimmed.includes('T')) {
            const [datePart] = trimmed.split('T');
            if (/^\d{4}-\d{2}-\d{2}$/.test(datePart)) {
                return datePart;
            }
        }
    }

    const parsedDate = new Date(value);
    if (Number.isNaN(parsedDate.getTime())) {
        return fallback;
    }

    return parsedDate.toISOString().split('T')[0];
};

const parseAmountInput = (value) => {
    const sanitized = `${value ?? ''}`.replace(/[^0-9.,-]/g, '');
    const normalized = sanitized.includes(',')
        ? sanitized.replace(/\./g, '').replace(',', '.')
        : sanitized.replace(/,/g, '');
    const parsed = Number.parseFloat(normalized);

    return Number.isNaN(parsed) ? 0 : parsed;
};

const formatAmount = (value, decimalPlaces = 2) => new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: decimalPlaces,
    maximumFractionDigits: decimalPlaces,
}).format(Number(value || 0));

const normalizeAmountInput = (value) => {
    const sanitized = `${value ?? ''}`.replace(/[^0-9.,]/g, '');
    const commaCount = (sanitized.match(/,/g) || []).length;
    const dotCount = (sanitized.match(/\./g) || []).length;

    if (commaCount > 0 && dotCount > 0) {
        return sanitized.replace(/\./g, '').replace(',', '.');
    }

    if (commaCount > 0) {
        return sanitized.replace(',', '.');
    }

    return sanitized;
};

const normalizeDimensionDetails = (details = []) => {
    if (!Array.isArray(details)) {
        return [];
    }

    return details.map((detail) => ({
        dimension_id: Number(detail?.dimension_id) || '',
        attributes: detail?.attributes && typeof detail.attributes === 'object' ? detail.attributes : {},
    }));
};

const formatDateByTimezone = (dateValue, timezone = 'UTC') => {
    if (!dateValue) {
        return '-';
    }

    const normalizedDateValue = typeof dateValue === 'string' ? dateValue.trim() : dateValue;
    const date = typeof normalizedDateValue === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(normalizedDateValue)
        ? new Date(`${normalizedDateValue}T12:00:00Z`)
        : new Date(normalizedDateValue);

    if (Number.isNaN(date.getTime())) {
        return '-';
    }

    const formatter = new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        timeZone: timezone,
    });
    const parts = formatter.formatToParts(date);
    const day = parts.find((part) => part.type === 'day')?.value ?? '';
    const month = (parts.find((part) => part.type === 'month')?.value ?? '').toLowerCase();
    const year = parts.find((part) => part.type === 'year')?.value ?? '';

    return `${day}-${month}-${year}`;
};

export default function Index() {
    const { manualJournals, deepLinkJournal, companies, branches, accountingPeriods, currencies, accounts, defaultEntryDate, errors, flash, filters, sort, yearOptions, monthOptions } = usePage().props;
    const fallbackEntryDate = defaultEntryDate || getTodayDate();
    const initialDecimalPlaces = Number(currencies[0]?.decimal_places ?? 2);

    const { data, setData, post, transform } = useForm({
        id: '', company_id: companies[0]?.id ?? '', branch_id: '', accounting_period_id: '', journal_no: '', entry_date: fallbackEntryDate, posting_date: '', reference_no: '', description: '',
        currency_code: currencies[0]?.code ?? 'IDR', exchange_rate: 1, status: 'draft', lines: [{ ...emptyLine }, { ...emptyLine }], isUpdate: false, isOpen: false,
    });
    const { data: importData, setData: setImportData, post: postImport, processing: importProcessing, errors: importErrors, clearErrors: clearImportErrors } = useForm({
        file: null,
        isOpen: false,
    });

    const [dimensionEditor, setDimensionEditor] = React.useState({ open: false, lineIndex: null, details: [] });
    const [accountSearchTerms, setAccountSearchTerms] = React.useState(['', '']);
    const [amountInputValues, setAmountInputValues] = React.useState(
        [{ debit: formatAmount(0, initialDecimalPlaces), credit: formatAmount(0, initialDecimalPlaces) }, { debit: formatAmount(0, initialDecimalPlaces), credit: formatAmount(0, initialDecimalPlaces) }],
    );
    const [listFilters, setListFilters] = React.useState({
        search: filters?.search ?? '',
        year: Number(filters?.year ?? new Date().getFullYear()),
        month: `${filters?.month ?? (new Date().getMonth() + 1)}`,
        branch_id: filters?.branch_id ?? 'all',
        status: filters?.status ?? 'all',
    });
    const [selectedJournalIds, setSelectedJournalIds] = React.useState([]);
    const [importNotice, setImportNotice] = React.useState(null);
    const openedDeepLinkJournalId = React.useRef(null);
    const filteredPeriods = accountingPeriods.filter((period) => period.company_id === Number(data.company_id));
    const filteredBranches = branches.filter((branch) => branch.company_id === Number(data.company_id));
    const filteredAccounts = accounts.filter((account) => account.company_id === Number(data.company_id) && Number(account.level) === 4);
    const selectedCurrency = currencies.find((currency) => currency.code === data.currency_code);
    const decimalPlaces = Number(selectedCurrency?.decimal_places ?? 2);
    const selectedAccountsById = Object.fromEntries(filteredAccounts.map((account) => [Number(account.id), account]));

    transform((formData) => ({ ...formData, _method: formData.isUpdate ? 'put' : 'post' }));

    const resetForm = () => {
        setData({
            id: '', company_id: companies[0]?.id ?? '', branch_id: '', accounting_period_id: '', journal_no: '', entry_date: fallbackEntryDate, posting_date: '', reference_no: '', description: '',
            currency_code: currencies[0]?.code ?? 'IDR', exchange_rate: 1, status: 'draft', lines: [{ ...emptyLine }, { ...emptyLine }], isUpdate: false, isOpen: false,
        });
        setDimensionEditor({ open: false, lineIndex: null, details: [] });
        setAccountSearchTerms(['', '']);
        setAmountInputValues([
            { debit: formatAmount(0, decimalPlaces), credit: formatAmount(0, decimalPlaces) },
            { debit: formatAmount(0, decimalPlaces), credit: formatAmount(0, decimalPlaces) },
        ]);
    };

    const submit = (e) => {
        e.preventDefault();
        post(data.isUpdate ? route('apps.manual-journals.update', data.id) : route('apps.manual-journals.store'), { onSuccess: resetForm });
    };

    const openJournalEditor = React.useCallback((journal) => {
        const lines = journal.lines.map((line) => ({ account_id: line.account_id, description: line.description ?? '', debit: Number(line.debit), credit: Number(line.credit), dimension_details: normalizeDimensionDetails(line.dimension_details_json ?? line.dimension_details) }));
        setData({
            ...journal,
            company_id: journal.company_id,
            branch_id: journal.branch_id ?? '',
            accounting_period_id: journal.accounting_period_id,
            entry_date: normalizeFormDate(journal.entry_date, fallbackEntryDate),
            posting_date: normalizeFormDate(journal.posting_date),
            exchange_rate: Number(journal.exchange_rate),
            lines,
            isUpdate: true,
            isOpen: true,
        });
        setAccountSearchTerms(lines.map(() => ''));
        setAmountInputValues(lines.map((line) => ({
            debit: formatAmount(line.debit, decimalPlaces),
            credit: formatAmount(line.credit, decimalPlaces),
        })));
    }, [decimalPlaces, fallbackEntryDate, setData]);

    React.useEffect(() => {
        if (!deepLinkJournal?.id || openedDeepLinkJournalId.current === deepLinkJournal.id) {
            return;
        }

        openJournalEditor(deepLinkJournal);
        openedDeepLinkJournalId.current = deepLinkJournal.id;
    }, [deepLinkJournal, openJournalEditor]);
    React.useEffect(() => {
        const successMessage = flash?.success ?? '';
        const errorMessage = flash?.error ?? '';

        if (successMessage.toLowerCase().includes('import')) {
            setImportNotice({ type: 'success', message: successMessage });
        } else if (errorMessage.toLowerCase().includes('import')) {
            setImportNotice({ type: 'error', message: errorMessage });
        }
    }, [flash?.success, flash?.error]);

    React.useEffect(() => {
        if (!importNotice) {
            return undefined;
        }

        const timeout = window.setTimeout(() => setImportNotice(null), 8000);

        return () => window.clearTimeout(timeout);
    }, [importNotice]);


    const updateLine = (index, field, value) => {
        const newLines = [...data.lines];
        newLines[index] = { ...newLines[index], [field]: value };
        setData('lines', newLines);
    };

    const updatePostingDate = (postingDate) => {
        const period = filteredPeriods.find((item) => postingDate >= item.start_date && postingDate <= item.end_date);
        setData({
            ...data,
            posting_date: postingDate,
            accounting_period_id: period?.id ?? '',
        });
    };

    const addLine = () => {
        setData('lines', [...data.lines, { ...emptyLine }]);
        setAccountSearchTerms((prev) => [...prev, '']);
        setAmountInputValues((prev) => [...prev, { debit: formatAmount(0, decimalPlaces), credit: formatAmount(0, decimalPlaces) }]);
    };
    const removeLine = (index) => {
        if (data.lines.length <= 2) {
            return;
        }

        setData('lines', data.lines.filter((_, i) => i !== index));
        setAccountSearchTerms((prev) => prev.filter((_, i) => i !== index));
        setAmountInputValues((prev) => prev.filter((_, i) => i !== index));
    };

    const updateAmountInput = (index, field, nextValue) => {
        setAmountInputValues((prev) => {
            const next = [...prev];
            const current = next[index] || { debit: formatAmount(0, decimalPlaces), credit: formatAmount(0, decimalPlaces) };
            next[index] = { ...current, [field]: nextValue };
            return next;
        });
    };

    const commitAmountInput = (index, field) => {
        const rawValue = amountInputValues[index]?.[field] ?? '';
        const parsedValue = parseAmountInput(rawValue);
        updateLine(index, field, parsedValue);
        updateAmountInput(index, field, formatAmount(parsedValue, decimalPlaces));
    };

    React.useEffect(() => {
        setAmountInputValues(data.lines.map((line) => ({
            debit: formatAmount(line.debit, decimalPlaces),
            credit: formatAmount(line.credit, decimalPlaces),
        })));
    }, [data.lines, decimalPlaces]);

    const openDimensionEditor = (lineIndex) => {
        const line = data.lines[lineIndex] ?? {};
        setDimensionEditor({
            open: true,
            lineIndex,
            details: normalizeDimensionDetails(line.dimension_details),
        });
    };

    const closeDimensionEditor = () => setDimensionEditor({ open: false, lineIndex: null, details: [] });

    const currentLine = dimensionEditor.lineIndex !== null ? data.lines[dimensionEditor.lineIndex] : null;
    const currentAccount = currentLine ? selectedAccountsById[Number(currentLine.account_id)] : null;
    const requiredDimensions = currentAccount?.requires_dimension ? (currentAccount.dimensions || []) : [];

    const upsertDimensionDetail = (dimensionId, patch) => {
        setDimensionEditor((prev) => {
            const nextDetails = [...prev.details];
            const index = nextDetails.findIndex((item) => Number(item.dimension_id) === Number(dimensionId));

            if (index === -1) {
                nextDetails.push({ dimension_id: Number(dimensionId), attributes: {}, ...patch });
            } else {
                nextDetails[index] = {
                    ...nextDetails[index],
                    ...patch,
                    attributes: {
                        ...(nextDetails[index].attributes || {}),
                        ...(patch.attributes || {}),
                    },
                };
            }

            return { ...prev, details: nextDetails };
        });
    };

    const updateDimensionAttribute = (dimensionId, key, value) => {
        const existing = dimensionEditor.details.find((item) => Number(item.dimension_id) === Number(dimensionId));
        upsertDimensionDetail(dimensionId, {
            attributes: {
                ...(existing?.attributes || {}),
                [key]: value,
            },
        });
    };

    const saveDimensionEditor = () => {
        if (dimensionEditor.lineIndex === null) {
            closeDimensionEditor();
            return;
        }

        updateLine(dimensionEditor.lineIndex, 'dimension_details', dimensionEditor.details);
        closeDimensionEditor();
    };

    const totalDebit = data.lines.reduce((sum, line) => sum + Number(line.debit || 0), 0);
    const totalCredit = data.lines.reduce((sum, line) => sum + Number(line.credit || 0), 0);
    const currentSortBy = sort?.by ?? 'posting_date';
    const currentSortDirection = sort?.direction ?? 'desc';
    const serializedFilters = React.useMemo(() => ({
        search: listFilters.search,
        year: Number(listFilters.year),
        month: listFilters.month,
        branch_id: listFilters.branch_id || 'all',
        status: listFilters.status || 'all',
    }), [listFilters]);

    const applyListFilters = React.useCallback((nextFilters) => {
        router.get(route('apps.manual-journals.index'), {
            ...nextFilters,
            sort_by: currentSortBy,
            sort_direction: currentSortDirection,
        }, {
            preserveState: true,
            replace: true,
        });
    }, [currentSortBy, currentSortDirection]);

    const submitSearch = (event) => {
        event.preventDefault();
        applyListFilters(serializedFilters);
    };

    const updateFilter = (field, value) => {
        const nextFilters = { ...listFilters, [field]: value };
        setListFilters(nextFilters);

        if (field !== 'search') {
            applyListFilters({
                search: nextFilters.search,
                year: Number(nextFilters.year),
                month: nextFilters.month,
                branch_id: nextFilters.branch_id || 'all',
                status: nextFilters.status || 'all',
            });
        }
    };

    const toggleSort = (field) => {
        const nextDirection = currentSortBy === field && currentSortDirection === 'asc' ? 'desc' : 'asc';

        router.get(route('apps.manual-journals.index'), {
            ...serializedFilters,
            sort_by: field,
            sort_direction: nextDirection,
        }, {
            preserveState: true,
            replace: true,
        });
    };

    const SortableHeader = ({ field, label, className = '' }) => (
        <Table.Th className={className}>
            <button type='button' className='inline-flex items-center gap-1 select-none' onClick={() => toggleSort(field)}>
                <span>{label}</span>
                <IconArrowsSort size={14} className={currentSortBy === field ? 'text-blue-500' : ''} />
            </button>
        </Table.Th>
    );

    const displayedSelectableIds = manualJournals.data.filter((journal) => journal.status !== 'posted').map((journal) => Number(journal.id));
    const selectedCount = selectedJournalIds.length;
    const allDisplayedSelected = displayedSelectableIds.length > 0 && displayedSelectableIds.every((id) => selectedJournalIds.includes(id));

    const toggleJournalSelection = (journalId) => {
        setSelectedJournalIds((prev) => (prev.includes(journalId) ? prev.filter((id) => id !== journalId) : [...prev, journalId]));
    };

    const toggleSelectAllDisplayed = () => {
        if (allDisplayedSelected) {
            setSelectedJournalIds((prev) => prev.filter((id) => !displayedSelectableIds.includes(id)));
            return;
        }

        setSelectedJournalIds((prev) => Array.from(new Set([...prev, ...displayedSelectableIds])));
    };

    const bulkPostSelected = () => {
        if (!selectedCount) {
            return;
        }

        router.post(route('apps.manual-journals.bulk-post'), {
            journal_ids: selectedJournalIds,
        }, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => setSelectedJournalIds([]),
        });
    };

    const openImportModal = () => {
        clearImportErrors();
        setImportNotice(null);
        setImportData({
            file: null,
            isOpen: true,
        });
    };

    const closeImportModal = () => {
        clearImportErrors();
        setImportData({
            file: null,
            isOpen: false,
        });
    };

    const submitImport = (event) => {
        event.preventDefault();
        postImport(route('apps.manual-journals.import'), {
            forceFormData: true,
            onSuccess: closeImportModal,
            onError: (formErrors) => {
                const message = formErrors?.file || Object.values(formErrors || {})[0] || 'Import manual jurnal gagal diproses. Periksa format file lalu coba lagi.';
                setImportNotice({ type: 'error', message });
            },
        });
    };

    const downloadImportTemplate = () => {
        window.location.href = route('apps.manual-journals.import-template');
    };

    return (
        <>
            <Head title='Manual Journal' />
            {importNotice && (
                <div className='fixed right-6 top-6 z-[120] max-w-lg rounded-xl border bg-white p-4 shadow-2xl dark:bg-gray-950 dark:border-gray-800' role='alert'>
                    <div className='flex items-start gap-3'>
                        <div className={`mt-0.5 ${importNotice.type === 'success' ? 'text-emerald-600' : 'text-red-600'}`}>
                            {importNotice.type === 'success' ? <IconCircleCheck size={22} strokeWidth={1.8} /> : <IconAlertCircle size={22} strokeWidth={1.8} />}
                        </div>
                        <div className='min-w-0 flex-1'>
                            <div className={`text-sm font-semibold ${importNotice.type === 'success' ? 'text-emerald-700 dark:text-emerald-300' : 'text-red-700 dark:text-red-300'}`}>
                                {importNotice.type === 'success' ? 'Import Berhasil' : 'Import Gagal'}
                            </div>
                            <div className='mt-1 text-sm text-gray-600 dark:text-gray-300'>{importNotice.message}</div>
                        </div>
                        <button type='button' className='rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-900 dark:hover:text-gray-200' onClick={() => setImportNotice(null)} aria-label='Tutup pesan import'>
                            <IconX size={18} strokeWidth={1.8} />
                        </button>
                    </div>
                </div>
            )}
            <div className='mb-2 flex justify-between items-center gap-2'>
                <div className='flex items-center gap-2'>
                    <Button type='button' icon={<IconCirclePlus size={20} strokeWidth={1.5} />} variant='gray' label='Tambah Manual Jurnal' onClick={() => setData('isOpen', true)} />
                    <Button type='button' icon={<IconFileImport size={20} strokeWidth={1.5} />} variant='gray' label='Import Excel/CSV' onClick={openImportModal} />
                    <Button type='button' icon={<IconFileSpreadsheet size={20} strokeWidth={1.5} />} variant='gray' label='Download Template Import' onClick={downloadImportTemplate} />
                </div>
                <form onSubmit={submitSearch} className='w-full md:w-10/12 grid grid-cols-1 md:grid-cols-4 gap-2'>
                    <div className='relative md:col-span-2'>
                        <input
                            type='text'
                            value={listFilters.search}
                            onChange={(event) => updateFilter('search', event.target.value)}
                            className='py-2 px-4 pr-11 block w-full rounded-lg text-sm border focus:outline-none focus:ring-0 focus:ring-gray-400 text-gray-700 bg-white border-gray-200 focus:border-gray-200 dark:focus:ring-gray-500 dark:focus:border-gray-800 dark:text-gray-200 dark:bg-gray-950 dark:border-gray-900'
                            placeholder='Cari manual jurnal...'
                        />
                        <button type='submit' className='absolute inset-y-0 right-0 flex items-center pr-3 text-gray-500'>
                            <IconSearch size={18} />
                        </button>
                    </div>
                    <select className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800' value={listFilters.year} onChange={(event) => updateFilter('year', Number(event.target.value))}>
                        {yearOptions.map((year) => <option key={year} value={year}>{year}</option>)}
                    </select>
                    <select className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800' value={listFilters.month} onChange={(event) => updateFilter('month', event.target.value)}>
                        {monthOptions.map((month) => <option key={month.value} value={month.value}>{month.label}</option>)}
                    </select>
                    <select className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800 md:col-span-2' value={listFilters.branch_id} onChange={(event) => updateFilter('branch_id', event.target.value)}>
                        <option value='all'>Semua Branch</option>
                        {branches.map((branch) => <option key={branch.id} value={branch.id}>{branch.code} - {branch.name}</option>)}
                    </select>
                    <select className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800 md:col-span-2' value={listFilters.status} onChange={(event) => updateFilter('status', event.target.value)}>
                        <option value='all'>Semua Status</option>
                        <option value='draft'>Draft</option>
                        <option value='pending_approval'>Pending Approval</option>
                        <option value='approved'>Approved</option>
                        <option value='posted'>Posted</option>
                        <option value='reversed'>Reversed</option>
                        <option value='cancelled'>Cancelled</option>
                    </select>
                </form>
            </div>
            <Modal
                show={data.isOpen && !dimensionEditor.open}
                maxWidth='6xl'
                closeable={!dimensionEditor.open}
                onClose={resetForm}
                title={data.isUpdate ? 'Ubah Manual Jurnal' : 'Tambah Manual Jurnal'}
                icon={<IconNotes size={20} strokeWidth={1.5} />}
            >
                <form onSubmit={submit} className='max-h-[80vh] overflow-y-auto'>
                    <div className='sticky top-0 z-10 space-y-3 border-b border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 pb-4'>
                        <div className='grid grid-cols-1 md:grid-cols-3 gap-3'>
                            <div className='flex flex-col gap-2'>
                                <label className='text-gray-600 text-sm'>Company</label>
                                <select className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800' value={data.company_id} onChange={(e) => {
                                    setData({ ...data, company_id: Number(e.target.value), branch_id: '', accounting_period_id: '', posting_date: '', lines: [{ ...emptyLine }, { ...emptyLine }] });
                                    setAccountSearchTerms(['', '']);
                                    setAmountInputValues([
                                        { debit: formatAmount(0, decimalPlaces), credit: formatAmount(0, decimalPlaces) },
                                        { debit: formatAmount(0, decimalPlaces), credit: formatAmount(0, decimalPlaces) },
                                    ]);
                                }}>{companies.map((company) => <option key={company.id} value={company.id}>{company.name}</option>)}</select>
                                {errors.company_id && <small className='text-xs text-red-500'>{errors.company_id}</small>}
                            </div>
                            <div className='flex flex-col gap-2'>
                                <label className='text-gray-600 text-sm'>Branch</label>
                                <select className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800' value={data.branch_id} onChange={(e) => setData('branch_id', e.target.value ? Number(e.target.value) : '')}>
                                    <option value=''>Tidak Spesifik</option>
                                    {filteredBranches.map((branch) => <option key={branch.id} value={branch.id}>{branch.code} - {branch.name}</option>)}
                                </select>
                                {errors.branch_id && <small className='text-xs text-red-500'>{errors.branch_id}</small>}
                            </div>
                            <Input label='Nomor Jurnal' type='text' value={data.journal_no} onChange={(e) => setData('journal_no', e.target.value)} errors={errors.journal_no} />
                        </div>

                        <div className='grid grid-cols-1 md:grid-cols-4 gap-3'>
                            <Input label='Tanggal Posting' type='date' value={data.posting_date} onChange={(e) => updatePostingDate(e.target.value)} errors={errors.posting_date} />
                            <div className='flex flex-col gap-2'>
                                <label className='text-gray-600 text-sm'>Currency</label>
                                <select className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800' value={data.currency_code} onChange={(e) => setData('currency_code', e.target.value)}>{currencies.map((currency) => <option key={currency.code} value={currency.code}>{currency.code} - {currency.name}</option>)}</select>
                            </div>
                            <Input label='Rate' type='number' min='0.0000000001' step='0.0000000001' value={data.exchange_rate} onChange={(e) => setData('exchange_rate', e.target.value)} errors={errors.exchange_rate} />
                            <div className='flex flex-col gap-2'>
                                <label className='text-gray-600 text-sm'>Status</label>
                                <select className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800' value={data.status} onChange={(e) => setData('status', e.target.value)}>{['draft', 'pending_approval', 'approved', 'posted', 'reversed', 'cancelled'].map((status) => <option key={status} value={status}>{status}</option>)}</select>
                            </div>
                        </div>

                        <div className='grid grid-cols-1 md:grid-cols-2 gap-3'>
                            <Input label='Referensi' type='text' value={data.reference_no} onChange={(e) => setData('reference_no', e.target.value)} errors={errors.reference_no} />
                            <Input label='Deskripsi' type='text' value={data.description} onChange={(e) => setData('description', e.target.value)} errors={errors.description} />
                        </div>

                        {errors.accounting_period_id && <small className='text-xs text-red-500'>{errors.accounting_period_id}</small>}
                    </div>

                    <div className='space-y-2 pt-4 border-b border-gray-200 dark:border-gray-800 pb-4'>
                        <div className='flex justify-between items-center'>
                            <h4 className='text-sm font-medium text-gray-700 dark:text-gray-300'>Baris Jurnal</h4>
                            <Button type='button' variant='blue' icon={<IconPlus size={16} strokeWidth={1.5} />} label='Tambah Baris' onClick={addLine} />
                        </div>
                        {data.lines.map((line, index) => {
                            const lineAccount = selectedAccountsById[Number(line.account_id)];
                            const lineRequiresDimension = Boolean(lineAccount?.requires_dimension);
                            const lineRequiredDimensions = lineRequiresDimension ? (lineAccount.dimensions || []) : [];
                            const filledDimensions = normalizeDimensionDetails(line.dimension_details).length;

                            return (
                                <div key={index} className='grid grid-cols-1 md:grid-cols-12 gap-2 items-end'>
                                    <div className='md:col-span-1'>
                                        <label className='text-gray-600 text-sm'>Cari COA</label>
                                        <input
                                            type='text'
                                            placeholder='COA...'
                                            maxLength={8}
                                            value={accountSearchTerms[index] ?? ''}
                                            onChange={(e) => {
                                                const newTerms = [...accountSearchTerms];
                                                newTerms[index] = e.target.value;
                                                setAccountSearchTerms(newTerms);
                                            }}
                                            className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800'
                                        />
                                    </div>
                                    <div className='md:col-span-2'>
                                        <label className='text-gray-600 text-sm'>Akun</label>
                                        <select className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800' value={line.account_id} onChange={(e) => updateLine(index, 'account_id', Number(e.target.value))}>
                                            <option value=''>Pilih akun</option>
                                            {filteredAccounts
                                                .filter((account) => {
                                                    const searchTerm = (accountSearchTerms[index] ?? '').trim().toLowerCase();
                                                    if (!searchTerm) {
                                                        return true;
                                                    }

                                                    return `${account.code} ${account.name}`.toLowerCase().includes(searchTerm);
                                                })
                                                .map((account) => <option key={account.id} value={account.id}>{account.code} - {account.name}</option>)}
                                        </select>
                                    </div>
                                    <div className='md:col-span-2'>
                                        <label className='text-gray-600 text-sm'>Informasi Dimensi</label>
                                        {lineRequiresDimension ? (
                                            <Button
                                                type='button'
                                                variant='gray'
                                                label={`${filledDimensions}/${lineRequiredDimensions.length} dimensi terisi`}
                                                onClick={() => openDimensionEditor(index)}
                                            />
                                        ) : (
                                            <input
                                                type='text'
                                                readOnly
                                                value='-'
                                                className='w-full px-3 py-1.5 border text-sm rounded-md bg-gray-100 text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800 cursor-not-allowed'
                                            />
                                        )}
                                    </div>
                                    <div className='md:col-span-2'><Input label='Deskripsi' type='text' value={line.description} onChange={(e) => updateLine(index, 'description', e.target.value)} /></div>
                                    <div className='md:col-span-2'>
                                        <Input
                                            label='Debit'
                                            type='text'
                                            inputMode='decimal'
                                            value={amountInputValues[index]?.debit ?? formatAmount(line.debit, decimalPlaces)}
                                            onChange={(e) => updateAmountInput(index, 'debit', normalizeAmountInput(e.target.value))}
                                            onBlur={() => commitAmountInput(index, 'debit')}
                                        />
                                    </div>
                                    <div className='md:col-span-2'>
                                        <Input
                                            label='Kredit'
                                            type='text'
                                            inputMode='decimal'
                                            value={amountInputValues[index]?.credit ?? formatAmount(line.credit, decimalPlaces)}
                                            onChange={(e) => updateAmountInput(index, 'credit', normalizeAmountInput(e.target.value))}
                                            onBlur={() => commitAmountInput(index, 'credit')}
                                        />
                                    </div>
                                    <div className='md:col-span-1 pb-1'><Button type='button' variant='rose' icon={<IconTrash size={16} strokeWidth={1.5} />} onClick={() => removeLine(index)} /></div>
                                </div>
                            );
                        })}
                        {(errors.lines || errors['lines.0.debit']) && <small className='text-xs text-red-500'>{errors.lines || errors['lines.0.debit']}</small>}
                        <div className='text-sm text-gray-600 dark:text-gray-300'>Total Debit: <b>{formatAmount(totalDebit, decimalPlaces)}</b> | Total Kredit: <b>{formatAmount(totalCredit, decimalPlaces)}</b></div>
                    </div>
                    <div className='pt-4 border-t border-gray-200 dark:border-gray-800'>
                        <Button type='submit' variant='gray' icon={<IconPencilCheck size={20} strokeWidth={1.5} />} label='Simpan' />
                    </div>
                </form>
            </Modal>
            <Modal
                show={importData.isOpen}
                maxWidth='lg'
                onClose={closeImportModal}
                title='Import Manual Jurnal (Excel/CSV)'
                icon={<IconFileImport size={20} strokeWidth={1.5} />}
            >
                <form onSubmit={submitImport} className='space-y-4'>
                    <div className='space-y-2'>
                        <label className='text-gray-600 text-sm'>File template import (.csv)</label>
                        <input
                            type='file'
                            accept='.csv,text/csv'
                            onChange={(event) => setImportData('file', event.target.files?.[0] ?? null)}
                            className='w-full px-3 py-2 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800'
                        />
                        <p className='text-xs text-gray-500'>Gunakan tombol download template agar format import sesuai.</p>
                        {importErrors.file && (
                            <div className='rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300'>
                                {importErrors.file}
                            </div>
                        )}
                    </div>
                    <div className='flex justify-end gap-2'>
                        <Button type='button' variant='gray' label='Batal' onClick={closeImportModal} />
                        <Button type='submit' variant='blue' label={importProcessing ? 'Importing...' : 'Import'} disabled={importProcessing} />
                    </div>
                </form>
            </Modal>

            <Modal
                show={dimensionEditor.open}
                maxWidth='4xl'
                closeable={false}
                onClose={closeDimensionEditor}
                title={currentAccount ? `Detail Dimensi - ${currentAccount.code} ${currentAccount.name}` : 'Detail Dimensi'}
            >
                <div className='space-y-4'>
                    {requiredDimensions.length === 0 && (
                        <div className='text-sm text-gray-500'>Akun pada baris ini tidak memiliki dimensi wajib.</div>
                    )}

                    {requiredDimensions.map((dimension) => {
                        const detail = dimensionEditor.details.find((item) => Number(item.dimension_id) === Number(dimension.id)) || { dimension_id: dimension.id, attributes: {} };
                        const attributes = Array.isArray(dimension.attribute_schema_json) ? dimension.attribute_schema_json : [];

                        return (
                            <div key={dimension.id} className='border border-gray-200 dark:border-gray-800 rounded-md p-3 space-y-2'>
                                <div className='font-medium text-sm text-gray-700 dark:text-gray-300'>
                                    {dimension.name} <span className='text-xs text-gray-500'>({dimension.type})</span>
                                </div>
                                {attributes.length === 0 && <div className='text-xs text-gray-500'>Dimensi ini belum memiliki atribut custom.</div>}
                                <div className='grid grid-cols-1 md:grid-cols-2 gap-2'>
                                    {attributes.map((attribute) => {
                                        const key = attribute?.key;
                                        const label = attribute?.label || key;
                                        const type = attribute?.type || 'text';
                                        const value = detail.attributes?.[key] ?? '';

                                        if (!key) {
                                            return null;
                                        }

                                        if (type === 'boolean') {
                                            return (
                                                <div key={`${dimension.id}-${key}`} className='flex flex-col gap-2'>
                                                    <label className='text-gray-600 text-sm'>
                                                        {label} {attribute?.is_required ? <span className='text-red-500'>*</span> : null}
                                                    </label>
                                                    <select
                                                        className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800'
                                                        value={value === '' ? '' : (value ? '1' : '0')}
                                                        onChange={(e) => updateDimensionAttribute(dimension.id, key, e.target.value === '' ? '' : e.target.value === '1')}
                                                    >
                                                        <option value=''>-</option>
                                                        <option value='1'>Ya</option>
                                                        <option value='0'>Tidak</option>
                                                    </select>
                                                </div>
                                            );
                                        }

                                        if (type === 'select') {
                                            const options = Array.isArray(attribute?.options) ? attribute.options : [];

                                            return (
                                                <div key={`${dimension.id}-${key}`} className='flex flex-col gap-2'>
                                                    <label className='text-gray-600 text-sm'>
                                                        {label} {attribute?.is_required ? <span className='text-red-500'>*</span> : null}
                                                    </label>
                                                    <select
                                                        className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800'
                                                        value={value}
                                                        onChange={(e) => updateDimensionAttribute(dimension.id, key, e.target.value)}
                                                    >
                                                        <option value=''>Pilih</option>
                                                        {options.map((option) => <option key={`${dimension.id}-${key}-${option}`} value={option}>{option}</option>)}
                                                    </select>
                                                </div>
                                            );
                                        }

                                        return (
                                            <Input
                                                key={`${dimension.id}-${key}`}
                                                label={(
                                                    <>
                                                        {label} {attribute?.is_required ? <span className='text-red-500'>*</span> : null}
                                                    </>
                                                )}
                                                type={type === 'number' || type === 'date' ? type : 'text'}
                                                value={value}
                                                onChange={(e) => updateDimensionAttribute(dimension.id, key, type === 'number' ? parseAmountInput(e.target.value) : e.target.value)}
                                            />
                                        );
                                    })}
                                </div>
                            </div>
                        );
                    })}

                    <div className='flex justify-end gap-2'>
                        <Button type='button' variant='blue' label='Simpan Dimensi' onClick={saveDimensionEditor} />
                        <Button type='button' variant='gray' label='Batal' onClick={closeDimensionEditor} />
                    </div>
                </div>
            </Modal>

            <Table.Card title='Data Manual Jurnal'>
                <div className='mb-3 flex justify-end'>
                    <Button
                        type='bulk'
                        variant='blue'
                        label={`Posting (${selectedCount})`}
                        disabled={!selectedCount}
                        onClick={bulkPostSelected}
                    />
                </div>
                <Table>
                    <Table.Thead>
                        <tr>
                            <Table.Th className='w-10'>
                                <input
                                    type='checkbox'
                                    checked={allDisplayedSelected}
                                    onChange={toggleSelectAllDisplayed}
                                />
                            </Table.Th>
                            <SortableHeader field='id' label='No' />
                            <SortableHeader field='company' label='Company' />
                            <SortableHeader field='branch' label='Branch' />
                            <SortableHeader field='journal_no' label='No Jurnal' />
                            <SortableHeader field='posting_date' label='Tanggal' />
                            <SortableHeader field='description' label='Deskripsi' />
                            <SortableHeader field='currency' label='Currency' />
                            <SortableHeader field='original_amount' label='Original Amount' />
                            <SortableHeader field='report_amount' label='Report Amount' />
                            <SortableHeader field='status' label='Status' />
                            <Table.Th className='w-40'>Aksi</Table.Th>
                        </tr>
                    </Table.Thead>
                    <Table.Tbody>
                        {manualJournals.data.length ? manualJournals.data.map((journal, i) => (
                            <tr key={journal.id} className='hover:bg-gray-100 dark:hover:bg-gray-900'>
                                <Table.Td>
                                    <input
                                        type='checkbox'
                                        disabled={journal.status === 'posted'}
                                        checked={selectedJournalIds.includes(Number(journal.id))}
                                        onChange={() => toggleJournalSelection(Number(journal.id))}
                                    />
                                </Table.Td>
                                <Table.Td>{i + 1 + ((manualJournals.current_page - 1) * manualJournals.per_page)}</Table.Td>
                                <Table.Td>{journal.company?.name}</Table.Td>
                                <Table.Td>{journal.branch ? `${journal.branch.code} - ${journal.branch.name}` : '-'}</Table.Td>
                                <Table.Td>{journal.journal_no}</Table.Td>
                                <Table.Td>{formatDateByTimezone(journal.posting_date, journal.company?.timezone ?? 'UTC')}</Table.Td>
                                <Table.Td>{journal.description}</Table.Td>
                                <Table.Td>{journal.currency_code}</Table.Td>
                                <Table.Td>{formatAmount(journal.total_debit, decimalPlaces)}</Table.Td>
                                <Table.Td>{formatAmount(Number(journal.total_debit || 0) * Number(journal.exchange_rate || 0), decimalPlaces)}</Table.Td>
                                <Table.Td className='capitalize'>{journal.status.replace('_', ' ')}</Table.Td>
                                <Table.Td><div className='flex gap-2'><Button type='modal' variant='orange' icon={<IconPencilCog size={16} strokeWidth={1.5} />} onClick={() => openJournalEditor(journal)} /><Button type='delete' variant='rose' icon={<IconTrash size={16} strokeWidth={1.5} />} url={route('apps.manual-journals.destroy', journal.id)} /></div></Table.Td>
                            </tr>
                        )) : <Table.Empty colSpan={12} message={<><div className='flex justify-center mb-2'><IconDatabaseOff size={24} /></div><span>Data manual jurnal tidak ditemukan.</span></>} />}
                    </Table.Tbody>
                </Table>
            </Table.Card>
            {manualJournals.last_page !== 1 && <Pagination links={manualJournals.links} />}
        </>
    );
}

Index.layout = (page) => <AppLayout children={page} />;
