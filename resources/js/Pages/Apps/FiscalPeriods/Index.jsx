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

    const { data, setData, post, transform } = useForm({
        id: '',
        company_id: companies[0]?.id ?? '',
        year_label: '',
        start_date: '',
        end_date: '',
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
            start_date: '',
            end_date: '',
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
                    <Input label='Label Tahun' type='text' value={data.year_label} onChange={(e) => setData('year_label', e.target.value)} errors={errors.year_label} />
                    <div className='grid grid-cols-2 gap-3'>
                        <Input label='Tanggal Mulai' type='date' value={data.start_date} onChange={(e) => setData('start_date', e.target.value)} errors={errors.start_date} />
                        <Input label='Tanggal Selesai' type='date' value={data.end_date} onChange={(e) => setData('end_date', e.target.value)} errors={errors.end_date} />
                    </div>
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
                            <tr key={period.id} className='hover:bg-gray-100 dark:hover:bg-gray-900'>
                                <Table.Td>{i + 1 + ((fiscalPeriods.current_page - 1) * fiscalPeriods.per_page)}</Table.Td>
                                <Table.Td>{period.company?.name}</Table.Td>
                                <Table.Td>{period.year_label}</Table.Td>
                                <Table.Td>{period.start_date} s/d {period.end_date}</Table.Td>
                                <Table.Td className='capitalize'>{period.status}</Table.Td>
                                <Table.Td>
                                    <div className='flex gap-2'>
                                        <Button type='modal' variant='orange' icon={<IconPencilCog size={16} strokeWidth={1.5} />} onClick={() => setData({ ...period, company_id: period.company_id, isUpdate: true, isOpen: true })} />
                                        <Button type='delete' variant='rose' icon={<IconTrash size={16} strokeWidth={1.5} />} url={route('apps.fiscal-periods.destroy', period.id)} />
                                    </div>
                                </Table.Td>
                            </tr>
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
