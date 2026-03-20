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
    const { branches, companies, errors } = usePage().props;

    const { data, setData, post } = useForm({
        id: '',
        company_id: companies[0]?.id ?? '',
        code: '',
        name: '',
        address: '',
        city: '',
        is_active: true,
        isUpdate: false,
        isOpen: false,
    });

    const resetForm = () => {
        setData({
            id: '',
            company_id: companies[0]?.id ?? '',
            code: '',
            name: '',
            address: '',
            city: '',
            is_active: true,
            isUpdate: false,
            isOpen: false,
        });
    };

    const submit = (e) => {
        e.preventDefault();

        post(data.isUpdate ? route('apps.branches.update', data.id) : route('apps.branches.store'), {
            onSuccess: resetForm,
            data: {
                ...data,
                _method: data.isUpdate ? 'put' : 'post',
            },
        });
    };

    return (
        <>
            <Head title='Branches' />
            <div className='mb-2 flex justify-between items-center gap-2'>
                <Button
                    type='button'
                    icon={<IconCirclePlus size={20} strokeWidth={1.5} />}
                    variant='gray'
                    label='Tambah Branch'
                    onClick={() => setData('isOpen', true)}
                />
                <div className='w-full md:w-4/12'>
                    <Search url={route('apps.branches.index')} placeholder='Cari branch...' />
                </div>
            </div>

            <Modal show={data.isOpen} onClose={resetForm} title={data.isUpdate ? 'Ubah Branch' : 'Tambah Branch'} icon={<IconHierarchy3 size={20} strokeWidth={1.5} />}>
                <form onSubmit={submit} className='space-y-4'>
                    <div className='flex flex-col gap-2'>
                        <label className='text-gray-600 text-sm'>Company</label>
                        <select className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800' value={data.company_id} onChange={(e) => setData('company_id', Number(e.target.value))}>
                            {companies.map((company) => <option key={company.id} value={company.id}>{company.name}</option>)}
                        </select>
                        {errors.company_id && <small className='text-xs text-red-500'>{errors.company_id}</small>}
                    </div>
                    <div className='grid grid-cols-2 gap-3'>
                        <Input label='Kode Branch' type='text' value={data.code} onChange={(e) => setData('code', e.target.value)} errors={errors.code} />
                        <Input label='Nama Branch' type='text' value={data.name} onChange={(e) => setData('name', e.target.value)} errors={errors.name} />
                    </div>
                    <Input label='Kota' type='text' value={data.city} onChange={(e) => setData('city', e.target.value)} errors={errors.city} />
                    <div className='flex flex-col gap-2'>
                        <label className='text-gray-600 text-sm'>Alamat</label>
                        <textarea className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800' rows={3} value={data.address} onChange={(e) => setData('address', e.target.value)} />
                        {errors.address && <small className='text-xs text-red-500'>{errors.address}</small>}
                    </div>
                    <div className='flex flex-col gap-2'>
                        <label className='text-gray-600 text-sm'>Status</label>
                        <select className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800' value={data.is_active ? '1' : '0'} onChange={(e) => setData('is_active', e.target.value === '1')}>
                            <option value='1'>Aktif</option>
                            <option value='0'>Nonaktif</option>
                        </select>
                    </div>

                    <Button type='submit' variant='gray' icon={<IconPencilCheck size={20} strokeWidth={1.5} />} label='Simpan' />
                </form>
            </Modal>

            <Table.Card title='Data Branch'>
                <Table>
                    <Table.Thead>
                        <tr>
                            <Table.Th>No</Table.Th>
                            <Table.Th>Company</Table.Th>
                            <Table.Th>Kode</Table.Th>
                            <Table.Th>Nama</Table.Th>
                            <Table.Th>Kota</Table.Th>
                            <Table.Th>Status</Table.Th>
                            <Table.Th className='w-40'></Table.Th>
                        </tr>
                    </Table.Thead>
                    <Table.Tbody>
                        {branches.data.length ? branches.data.map((branch, i) => (
                            <tr key={branch.id} className='hover:bg-gray-100 dark:hover:bg-gray-900'>
                                <Table.Td>{i + 1 + ((branches.current_page - 1) * branches.per_page)}</Table.Td>
                                <Table.Td>{branch.company?.name}</Table.Td>
                                <Table.Td>{branch.code}</Table.Td>
                                <Table.Td>{branch.name}</Table.Td>
                                <Table.Td>{branch.city || '-'}</Table.Td>
                                <Table.Td>{branch.is_active ? 'Aktif' : 'Nonaktif'}</Table.Td>
                                <Table.Td>
                                    <div className='flex gap-2'>
                                        <Button type='modal' variant='orange' icon={<IconPencilCog size={16} strokeWidth={1.5} />} onClick={() => setData({ ...branch, isUpdate: true, isOpen: true })} />
                                        <Button type='delete' variant='rose' icon={<IconTrash size={16} strokeWidth={1.5} />} url={route('apps.branches.destroy', branch.id)} />
                                    </div>
                                </Table.Td>
                            </tr>
                        )) : (
                            <Table.Empty colSpan={7} message={<><div className='flex justify-center mb-2'><IconDatabaseOff size={24} /></div><span>Data branch tidak ditemukan.</span></>} />
                        )}
                    </Table.Tbody>
                </Table>
            </Table.Card>
            {branches.last_page !== 1 && <Pagination links={branches.links} />}
        </>
    );
}

Index.layout = (page) => <AppLayout children={page} />;
