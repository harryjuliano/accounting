import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm, usePage } from '@inertiajs/react';
import React from 'react';
import Button from '@/Components/Button';
import Modal from '@/Components/Modal';
import Input from '@/Components/Input';
import Table from '@/Components/Table';
import Search from '@/Components/Search';
import Pagination from '@/Components/Pagination';
import { IconCirclePlus, IconDatabaseOff, IconHierarchy3, IconPencilCheck, IconPencilCog, IconTrash } from '@tabler/icons-react';

export default function Index() {
    const { dimensions, companies, errors } = usePage().props;

    const { data, setData, post, transform } = useForm({
        id: '',
        company_id: companies[0]?.id ?? '',
        code: '',
        name: '',
        type: 'department',
        is_mandatory: false,
        is_active: true,
        isUpdate: false,
        isOpen: false,
    });

    transform((formData) => ({ ...formData, _method: formData.isUpdate ? 'put' : 'post' }));

    const resetForm = () => {
        setData({
            id: '', company_id: companies[0]?.id ?? '', code: '', name: '', type: 'department',
            is_mandatory: false, is_active: true, isUpdate: false, isOpen: false,
        });
    };

    const submit = (e) => {
        e.preventDefault();
        post(data.isUpdate ? route('apps.dimensions.update', data.id) : route('apps.dimensions.store'), { onSuccess: resetForm });
    };

    return (
        <>
            <Head title='Dimensions' />
            <div className='mb-2 flex justify-between items-center gap-2'>
                <Button type='button' icon={<IconCirclePlus size={20} strokeWidth={1.5} />} variant='gray' label='Tambah Dimension' onClick={() => setData('isOpen', true)} />
                <div className='w-full md:w-4/12'>
                    <Search url={route('apps.dimensions.index')} placeholder='Cari dimension...' />
                </div>
            </div>

            <Modal show={data.isOpen} onClose={resetForm} title={data.isUpdate ? 'Ubah Dimension' : 'Tambah Dimension'} icon={<IconHierarchy3 size={20} strokeWidth={1.5} />}>
                <form onSubmit={submit} className='space-y-4'>
                    <div className='flex flex-col gap-2'>
                        <label className='text-gray-600 text-sm'>Company</label>
                        <select className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800' value={data.company_id} onChange={(e) => setData('company_id', Number(e.target.value))}>
                            {companies.map((company) => <option key={company.id} value={company.id}>{company.name}</option>)}
                        </select>
                        {errors.company_id && <small className='text-xs text-red-500'>{errors.company_id}</small>}
                    </div>
                    <div className='grid grid-cols-2 gap-3'>
                        <Input label='Kode' type='text' value={data.code} onChange={(e) => setData('code', e.target.value)} errors={errors.code} />
                        <Input label='Nama' type='text' value={data.name} onChange={(e) => setData('name', e.target.value)} errors={errors.name} />
                    </div>
                    <div className='grid grid-cols-2 gap-3'>
                        <div className='flex flex-col gap-2'>
                            <label className='text-gray-600 text-sm'>Tipe</label>
                            <select className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800' value={data.type} onChange={(e) => setData('type', e.target.value)}>
                                {['branch', 'department', 'cost_center', 'project', 'customer', 'vendor', 'employee', 'custom'].map((type) => <option key={type} value={type}>{type}</option>)}
                            </select>
                        </div>
                        <div className='flex flex-col gap-2'>
                            <label className='text-gray-600 text-sm'>Mandatory</label>
                            <select className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800' value={data.is_mandatory ? '1' : '0'} onChange={(e) => setData('is_mandatory', e.target.value === '1')}>
                                <option value='0'>Tidak</option><option value='1'>Ya</option>
                            </select>
                        </div>
                    </div>
                    <div className='flex flex-col gap-2'>
                        <label className='text-gray-600 text-sm'>Status</label>
                        <select className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800' value={data.is_active ? '1' : '0'} onChange={(e) => setData('is_active', e.target.value === '1')}>
                            <option value='1'>Aktif</option><option value='0'>Nonaktif</option>
                        </select>
                    </div>
                    <Button type='submit' variant='gray' icon={<IconPencilCheck size={20} strokeWidth={1.5} />} label='Simpan' />
                </form>
            </Modal>

            <Table.Card title='Data Dimensions'>
                <Table>
                    <Table.Thead><tr><Table.Th>No</Table.Th><Table.Th>Company</Table.Th><Table.Th>Kode</Table.Th><Table.Th>Nama</Table.Th><Table.Th>Tipe</Table.Th><Table.Th>Status</Table.Th><Table.Th className='w-40'></Table.Th></tr></Table.Thead>
                    <Table.Tbody>
                        {dimensions.data.length ? dimensions.data.map((dimension, i) => (
                            <tr key={dimension.id} className='hover:bg-gray-100 dark:hover:bg-gray-900'>
                                <Table.Td>{i + 1 + ((dimensions.current_page - 1) * dimensions.per_page)}</Table.Td>
                                <Table.Td>{dimension.company?.name}</Table.Td><Table.Td>{dimension.code}</Table.Td><Table.Td>{dimension.name}</Table.Td>
                                <Table.Td className='capitalize'>{dimension.type}</Table.Td><Table.Td>{dimension.is_active ? 'Aktif' : 'Nonaktif'}</Table.Td>
                                <Table.Td><div className='flex gap-2'>
                                    <Button type='modal' variant='orange' icon={<IconPencilCog size={16} strokeWidth={1.5} />} onClick={() => setData({ ...dimension, isUpdate: true, isOpen: true })} />
                                    <Button type='delete' variant='rose' icon={<IconTrash size={16} strokeWidth={1.5} />} url={route('apps.dimensions.destroy', dimension.id)} />
                                </div></Table.Td>
                            </tr>
                        )) : <Table.Empty colSpan={7} message={<><div className='flex justify-center mb-2'><IconDatabaseOff size={24} /></div><span>Data dimension tidak ditemukan.</span></>} />}
                    </Table.Tbody>
                </Table>
            </Table.Card>
            {dimensions.last_page !== 1 && <Pagination links={dimensions.links} />}
        </>
    );
}

Index.layout = (page) => <AppLayout children={page} />;
