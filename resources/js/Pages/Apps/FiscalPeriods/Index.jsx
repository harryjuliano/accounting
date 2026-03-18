import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm, usePage } from '@inertiajs/react';
import React from 'react';
import Button from '@/Components/Button';
import Modal from '@/Components/Modal';
import Input from '@/Components/Input';
import Table from '@/Components/Table';
import Search from '@/Components/Search';
import Pagination from '@/Components/Pagination';
import { IconCalendarStats, IconCirclePlus, IconDatabaseOff, IconPencilCheck, IconPencilCog, IconTrash } from '@tabler/icons-react';

export default function Index() {
    const { fiscalPeriods, companies, errors } = usePage().props;
    const { post: postAction } = useForm({});

    const { data, setData, post, transform } = useForm({
        id: '',
        company_id: companies[0]?.id ?? '',
        year_label: '',
        status: 'draft',
        isUpdate: false,
        isOpen: false,
    });

    transform((formData) => ({
        ...formData,
        _method: formData.isUpdate ? 'put' : 'post',
    }));

    const resetForm = () => {
        setData({
            id: '',
            company_id: companies[0]?.id ?? '',
            year_label: '',
            status: 'draft',
            isUpdate: false,
            isOpen: false,
        });
    };

    const submit = (e) => {
        e.preventDefault();

        const targetRoute = data.isUpdate ? route('apps.fiscal-periods.update', data.id) : route('apps.fiscal-periods.store');
        post(targetRoute, { onSuccess: resetForm });
    };

    const toggleMonthlyClose = (fiscalYearId, accountingPeriodId) => {
        postAction(route('apps.fiscal-periods.accounting-periods.toggle-close', [fiscalYearId, accountingPeriodId]));
    };

    const hardCloseYear = (fiscalYearId) => {
        postAction(route('apps.fiscal-periods.hard-close', fiscalYearId));
    };

    return (
        <>
            <Head title='Fiscal Period' />
            <div className='mb-2 flex justify-between items-center gap-2'>
                <Button
                    type='button'
                    icon={<IconCirclePlus size={20} strokeWidth={1.5} />}
                    variant='gray'
                    label='Tambah Fiscal Period'
                    onClick={() => setData('isOpen', true)}
                />
                <div className='w-full md:w-4/12'>
                    <Search url={route('apps.fiscal-periods.index')} placeholder='Cari fiscal period...' />
                </div>
            </div>

            <Modal show={data.isOpen} onClose={resetForm} title={data.isUpdate ? 'Ubah Fiscal Period' : 'Tambah Fiscal Period'} icon={<IconCalendarStats size={20} strokeWidth={1.5} />}>
                <form onSubmit={submit} className='space-y-4'>
                    <div className='flex flex-col gap-2'>
                        <label className='text-gray-600 text-sm'>Company</label>
                        <select className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800' value={data.company_id} onChange={(e) => setData('company_id', Number(e.target.value))}>
                            {companies.map((company) => (
                                <option key={company.id} value={company.id}>{company.name}</option>
                            ))}
                        </select>
                        {errors.company_id && <small className='text-xs text-red-500'>{errors.company_id}</small>}
                    </div>
                    <Input label='Tahun Fiskal' type='number' min='1900' max='2200' value={data.year_label} onChange={(e) => setData('year_label', e.target.value)} errors={errors.year_label} />
                    <p className='text-xs text-gray-500 dark:text-gray-400'>Saat disimpan, sistem otomatis membuat 12 periode bulanan berdasarkan tahun fiskal yang diinput.</p>
                    <div className='flex flex-col gap-2'>
                        <label className='text-gray-600 text-sm'>Status</label>
                        <select className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800' value={data.status} onChange={(e) => setData('status', e.target.value)}>
                            <option value='draft'>Draft</option>
                            <option value='open'>Open</option>
                            <option value='closed'>Closed</option>
                        </select>
                    </div>

                    <Button type='submit' variant='gray' icon={<IconPencilCheck size={20} strokeWidth={1.5} />} label='Simpan' />
                </form>
            </Modal>

            <Table.Card title='Data Fiscal Period'>
                <p className='text-xs text-gray-500 dark:text-gray-400 mb-4'>
                    Tabel di bawah menampilkan level tahun fiskal. Gunakan <strong>Soft Close</strong> per bulan untuk monthly close, lalu <strong>Hard Close Year</strong> di akhir tahun untuk penutupan tahunan.
                </p>
                <Table>
                    <Table.Thead>
                        <tr>
                            <Table.Th>No</Table.Th>
                            <Table.Th>Company</Table.Th>
                            <Table.Th>Label</Table.Th>
                            <Table.Th>Periode</Table.Th>
                            <Table.Th>Status</Table.Th>
                            <Table.Th className='w-40'></Table.Th>
                        </tr>
                    </Table.Thead>
                    <Table.Tbody>
                        {fiscalPeriods.data.length ? fiscalPeriods.data.map((period, i) => (
                            <React.Fragment key={period.id}>
                                <tr className='hover:bg-gray-100 dark:hover:bg-gray-900'>
                                    <Table.Td className='text-gray-800 dark:text-gray-100'>{i + 1 + ((fiscalPeriods.current_page - 1) * fiscalPeriods.per_page)}</Table.Td>
                                    <Table.Td className='text-gray-800 dark:text-gray-100'>{period.company?.name}</Table.Td>
                                    <Table.Td className='text-gray-800 dark:text-gray-100'>{period.year_label}</Table.Td>
                                    <Table.Td className='text-gray-800 dark:text-gray-100'>{period.start_date} s/d {period.end_date}</Table.Td>
                                    <Table.Td className='capitalize text-gray-800 dark:text-gray-100'>{period.status}</Table.Td>
                                    <Table.Td>
                                        <div className='flex gap-2'>
                                            <button
                                                type='button'
                                                onClick={() => hardCloseYear(period.id)}
                                                disabled={period.status === 'closed'}
                                                className='px-3 py-2 rounded-lg text-xs font-semibold border bg-white text-gray-700 hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed dark:bg-gray-900 dark:text-gray-200 dark:border-gray-800'
                                            >
                                                Hard Close Year
                                            </button>
                                            <Button type='modal' variant='orange' icon={<IconPencilCog size={16} strokeWidth={1.5} />} onClick={() => setData({ ...period, company_id: period.company_id, isUpdate: true, isOpen: true })} />
                                            <Button type='delete' variant='rose' icon={<IconTrash size={16} strokeWidth={1.5} />} url={route('apps.fiscal-periods.destroy', period.id)} />
                                        </div>
                                    </Table.Td>
                                </tr>
                                <tr className='bg-gray-50 dark:bg-gray-950'>
                                    <td colSpan={6} className='px-4 py-3'>
                                        <div className='overflow-x-auto'>
                                            <table className='w-full text-xs md:text-sm text-gray-700 dark:text-gray-200'>
                                                <thead>
                                                    <tr className='text-left text-gray-600 dark:text-gray-300'>
                                                        <th className='py-2'>Periode Bulan</th>
                                                        <th className='py-2'>Tanggal</th>
                                                        <th className='py-2'>Status Close</th>
                                                        <th className='py-2 text-right'>Aksi</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {(period.accounting_periods ?? []).sort((a, b) => a.period_no - b.period_no).map((month) => (
                                                        <tr key={month.id} className='border-t border-gray-200 dark:border-gray-800'>
                                                            <td className='py-2 text-gray-700 dark:text-gray-100'>{month.period_name}</td>
                                                            <td className='py-2 text-gray-700 dark:text-gray-100'>{month.start_date} s/d {month.end_date}</td>
                                                            <td className='py-2 capitalize text-gray-700 dark:text-gray-100'>{month.status}</td>
                                                            <td className='py-2 text-right'>
                                                                <button
                                                                    type='button'
                                                                    disabled={!['open', 'soft_closed'].includes(month.status)}
                                                                    onClick={() => toggleMonthlyClose(period.id, month.id)}
                                                                    className='px-3 py-1 rounded-md border text-xs font-semibold text-gray-700 dark:text-gray-100 hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed dark:hover:bg-gray-900'
                                                                >
                                                                    {month.status === 'open' ? 'Soft Close' : 'Reopen'}
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    </td>
                                </tr>
                            </React.Fragment>
                        )) : (
                            <Table.Empty colSpan={6} message={<><div className='flex justify-center mb-2'><IconDatabaseOff size={24} /></div><span>Data fiscal period tidak ditemukan.</span></>} />
                        )}
                    </Table.Tbody>
                </Table>
            </Table.Card>
            {fiscalPeriods.last_page !== 1 && <Pagination links={fiscalPeriods.links} />}
        </>
    );
}

Index.layout = (page) => <AppLayout children={page} />;
