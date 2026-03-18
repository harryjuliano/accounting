import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm, usePage } from '@inertiajs/react';
import React from 'react';
import Button from '@/Components/Button';
import Modal from '@/Components/Modal';
import Input from '@/Components/Input';
import Table from '@/Components/Table';
import Search from '@/Components/Search';
import Pagination from '@/Components/Pagination';
import { IconCirclePlus, IconDatabaseOff, IconNotes, IconPencilCheck, IconPencilCog, IconPlus, IconTrash } from '@tabler/icons-react';

const emptyLine = { account_id: '', description: '', debit: 0, credit: 0 };

export default function Index() {
    const { manualJournals, companies, accountingPeriods, currencies, accounts, errors } = usePage().props;

    const { data, setData, post, transform } = useForm({
        id: '', company_id: companies[0]?.id ?? '', accounting_period_id: '', journal_no: '', entry_date: '', posting_date: '', reference_no: '', description: '',
        currency_code: currencies[0]?.code ?? 'IDR', exchange_rate: 1, status: 'draft', lines: [{ ...emptyLine }, { ...emptyLine }], isUpdate: false, isOpen: false,
    });

    transform((formData) => ({ ...formData, _method: formData.isUpdate ? 'put' : 'post' }));

    const resetForm = () => setData({
        id: '', company_id: companies[0]?.id ?? '', accounting_period_id: '', journal_no: '', entry_date: '', posting_date: '', reference_no: '', description: '',
        currency_code: currencies[0]?.code ?? 'IDR', exchange_rate: 1, status: 'draft', lines: [{ ...emptyLine }, { ...emptyLine }], isUpdate: false, isOpen: false,
    });

    const submit = (e) => {
        e.preventDefault();
        post(data.isUpdate ? route('apps.manual-journals.update', data.id) : route('apps.manual-journals.store'), { onSuccess: resetForm });
    };

    const filteredPeriods = accountingPeriods.filter((period) => period.company_id === Number(data.company_id));
    const filteredAccounts = accounts.filter((account) => account.company_id === Number(data.company_id));

    const updateLine = (index, field, value) => {
        const newLines = [...data.lines];
        newLines[index] = { ...newLines[index], [field]: value };
        setData('lines', newLines);
    };

    const addLine = () => setData('lines', [...data.lines, { ...emptyLine }]);
    const removeLine = (index) => data.lines.length > 2 && setData('lines', data.lines.filter((_, i) => i !== index));

    const totalDebit = data.lines.reduce((sum, line) => sum + Number(line.debit || 0), 0);
    const totalCredit = data.lines.reduce((sum, line) => sum + Number(line.credit || 0), 0);

    return (
        <>
            <Head title='Manual Journal' />
            <div className='mb-2 flex justify-between items-center gap-2'>
                <Button type='button' icon={<IconCirclePlus size={20} strokeWidth={1.5} />} variant='gray' label='Tambah Manual Jurnal' onClick={() => setData('isOpen', true)} />
                <div className='w-full md:w-4/12'><Search url={route('apps.manual-journals.index')} placeholder='Cari manual jurnal...' /></div>
            </div>
            <Modal show={data.isOpen} onClose={resetForm} title={data.isUpdate ? 'Ubah Manual Jurnal' : 'Tambah Manual Jurnal'} icon={<IconNotes size={20} strokeWidth={1.5} />}>
                <form onSubmit={submit} className='space-y-4'>
                    <div className='grid grid-cols-2 gap-3'>
                        <div className='flex flex-col gap-2'>
                            <label className='text-gray-600 text-sm'>Company</label>
                            <select className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800' value={data.company_id} onChange={(e) => setData({ ...data, company_id: Number(e.target.value), accounting_period_id: '', lines: [{ ...emptyLine }, { ...emptyLine }] })}>{companies.map((company) => <option key={company.id} value={company.id}>{company.name}</option>)}</select>
                            {errors.company_id && <small className='text-xs text-red-500'>{errors.company_id}</small>}
                        </div>
                        <div className='flex flex-col gap-2'>
                            <label className='text-gray-600 text-sm'>Periode</label>
                            <select className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800' value={data.accounting_period_id} onChange={(e) => setData('accounting_period_id', Number(e.target.value))}>
                                <option value=''>Pilih periode</option>
                                {filteredPeriods.map((period) => <option key={period.id} value={period.id}>{period.period_name}</option>)}
                            </select>
                            {errors.accounting_period_id && <small className='text-xs text-red-500'>{errors.accounting_period_id}</small>}
                        </div>
                    </div>
                    <div className='grid grid-cols-2 gap-3'>
                        <Input label='Nomor Jurnal' type='text' value={data.journal_no} onChange={(e) => setData('journal_no', e.target.value)} errors={errors.journal_no} />
                        <Input label='Referensi' type='text' value={data.reference_no} onChange={(e) => setData('reference_no', e.target.value)} errors={errors.reference_no} />
                    </div>
                    <div className='grid grid-cols-2 gap-3'>
                        <Input label='Tanggal Entry' type='date' value={data.entry_date} onChange={(e) => setData('entry_date', e.target.value)} errors={errors.entry_date} />
                        <Input label='Tanggal Posting' type='date' value={data.posting_date} onChange={(e) => setData('posting_date', e.target.value)} errors={errors.posting_date} />
                    </div>
                    <div className='grid grid-cols-3 gap-3'>
                        <div className='flex flex-col gap-2'>
                            <label className='text-gray-600 text-sm'>Currency</label>
                            <select className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800' value={data.currency_code} onChange={(e) => setData('currency_code', e.target.value)}>{currencies.map((currency) => <option key={currency.code} value={currency.code}>{currency.code} - {currency.name}</option>)}</select>
                        </div>
                        <Input label='Kurs' type='number' min='0.0000000001' step='0.0000000001' value={data.exchange_rate} onChange={(e) => setData('exchange_rate', e.target.value)} errors={errors.exchange_rate} />
                        <div className='flex flex-col gap-2'>
                            <label className='text-gray-600 text-sm'>Status</label>
                            <select className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800' value={data.status} onChange={(e) => setData('status', e.target.value)}>{['draft', 'pending_approval', 'approved', 'posted', 'reversed', 'cancelled'].map((status) => <option key={status} value={status}>{status}</option>)}</select>
                        </div>
                    </div>
                    <Input label='Deskripsi' type='text' value={data.description} onChange={(e) => setData('description', e.target.value)} errors={errors.description} />

                    <div className='space-y-2'>
                        <div className='flex justify-between items-center'>
                            <h4 className='text-sm font-medium text-gray-700 dark:text-gray-300'>Baris Jurnal</h4>
                            <Button type='button' variant='blue' icon={<IconPlus size={16} strokeWidth={1.5} />} label='Tambah Baris' onClick={addLine} />
                        </div>
                        {data.lines.map((line, index) => (
                            <div key={index} className='grid grid-cols-12 gap-2 items-end'>
                                <div className='col-span-4'>
                                    <label className='text-gray-600 text-sm'>Akun</label>
                                    <select className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800' value={line.account_id} onChange={(e) => updateLine(index, 'account_id', Number(e.target.value))}>
                                        <option value=''>Pilih akun</option>
                                        {filteredAccounts.map((account) => <option key={account.id} value={account.id}>{account.code} - {account.name}</option>)}
                                    </select>
                                </div>
                                <div className='col-span-3'><Input label='Deskripsi' type='text' value={line.description} onChange={(e) => updateLine(index, 'description', e.target.value)} /></div>
                                <div className='col-span-2'><Input label='Debit' type='number' min='0' step='0.01' value={line.debit} onChange={(e) => updateLine(index, 'debit', e.target.value)} /></div>
                                <div className='col-span-2'><Input label='Kredit' type='number' min='0' step='0.01' value={line.credit} onChange={(e) => updateLine(index, 'credit', e.target.value)} /></div>
                                <div className='col-span-1 pb-1'><Button type='button' variant='rose' icon={<IconTrash size={16} strokeWidth={1.5} />} onClick={() => removeLine(index)} /></div>
                            </div>
                        ))}
                        {(errors.lines || errors['lines.0.debit']) && <small className='text-xs text-red-500'>{errors.lines || errors['lines.0.debit']}</small>}
                        <div className='text-sm text-gray-600 dark:text-gray-300'>Total Debit: <b>{totalDebit.toFixed(2)}</b> | Total Kredit: <b>{totalCredit.toFixed(2)}</b></div>
                    </div>
                    <Button type='submit' variant='gray' icon={<IconPencilCheck size={20} strokeWidth={1.5} />} label='Simpan' />
                </form>
            </Modal>

            <Table.Card title='Data Manual Jurnal'>
                <Table>
                    <Table.Thead><tr><Table.Th>No</Table.Th><Table.Th>Company</Table.Th><Table.Th>No Jurnal</Table.Th><Table.Th>Tanggal</Table.Th><Table.Th>Deskripsi</Table.Th><Table.Th>Total</Table.Th><Table.Th>Status</Table.Th><Table.Th className='w-40'></Table.Th></tr></Table.Thead>
                    <Table.Tbody>
                        {manualJournals.data.length ? manualJournals.data.map((journal, i) => (
                            <tr key={journal.id} className='hover:bg-gray-100 dark:hover:bg-gray-900'>
                                <Table.Td>{i + 1 + ((manualJournals.current_page - 1) * manualJournals.per_page)}</Table.Td>
                                <Table.Td>{journal.company?.name}</Table.Td>
                                <Table.Td>{journal.journal_no}</Table.Td>
                                <Table.Td>{journal.entry_date}</Table.Td>
                                <Table.Td>{journal.description}</Table.Td>
                                <Table.Td>{Number(journal.total_debit).toLocaleString()}</Table.Td>
                                <Table.Td className='capitalize'>{journal.status.replace('_', ' ')}</Table.Td>
                                <Table.Td><div className='flex gap-2'><Button type='modal' variant='orange' icon={<IconPencilCog size={16} strokeWidth={1.5} />} onClick={() => setData({ ...journal, company_id: journal.company_id, accounting_period_id: journal.accounting_period_id, exchange_rate: Number(journal.exchange_rate), lines: journal.lines.map((line) => ({ account_id: line.account_id, description: line.description ?? '', debit: Number(line.debit), credit: Number(line.credit) })), isUpdate: true, isOpen: true })} /><Button type='delete' variant='rose' icon={<IconTrash size={16} strokeWidth={1.5} />} url={route('apps.manual-journals.destroy', journal.id)} /></div></Table.Td>
                            </tr>
                        )) : <Table.Empty colSpan={8} message={<><div className='flex justify-center mb-2'><IconDatabaseOff size={24} /></div><span>Data manual jurnal tidak ditemukan.</span></>} />}
                    </Table.Tbody>
                </Table>
            </Table.Card>
            {manualJournals.last_page !== 1 && <Pagination links={manualJournals.links} />}
        </>
    );
}

Index.layout = (page) => <AppLayout children={page} />;
