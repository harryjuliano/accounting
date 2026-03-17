import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm, usePage } from '@inertiajs/react';
import React from 'react';
import Button from '@/Components/Button';
import Modal from '@/Components/Modal';
import Input from '@/Components/Input';
import Table from '@/Components/Table';
import Search from '@/Components/Search';
import Pagination from '@/Components/Pagination';
import { IconBuilding, IconCirclePlus, IconDatabaseOff, IconPencilCheck, IconPencilCog, IconTrash } from '@tabler/icons-react';

export default function Index() {
    const { companies, errors } = usePage().props;

    const { data, setData, post, transform } = useForm({
        id: '',
        code: '',
        name: '',
        legal_name: '',
        tax_id: '',
        base_currency_code: 'IDR',
        country_code: 'ID',
        timezone: 'Asia/Jakarta',
        fiscal_year_start_month: 1,
        is_active: true,
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
            code: '',
            name: '',
            legal_name: '',
            tax_id: '',
            base_currency_code: 'IDR',
            country_code: 'ID',
            timezone: 'Asia/Jakarta',
            fiscal_year_start_month: 1,
            is_active: true,
            isUpdate: false,
            isOpen: false,
        });
    };

    const submit = (e) => {
        e.preventDefault();

        const targetRoute = data.isUpdate ? route('apps.companies.update', data.id) : route('apps.companies.store');
        post(targetRoute, { onSuccess: resetForm });
    };

    return (
        <>
            <Head title='Company' />
            <div className='mb-2 flex justify-between items-center gap-2'>
                <Button
                    type='button'
                    icon={<IconCirclePlus size={20} strokeWidth={1.5} />}
                    variant='gray'
                    label='Tambah Company'
                    onClick={() => setData('isOpen', true)}
                />
                <div className='w-full md:w-4/12'>
                    <Search url={route('apps.companies.index')} placeholder='Cari company...' />
                </div>
            </div>

            <Modal show={data.isOpen} onClose={resetForm} title={data.isUpdate ? 'Ubah Company' : 'Tambah Company'} icon={<IconBuilding size={20} strokeWidth={1.5} />}>
                <form onSubmit={submit} className='space-y-4'>
                    <Input label='Kode' type='text' value={data.code} onChange={(e) => setData('code', e.target.value)} errors={errors.code} />
                    <Input label='Nama' type='text' value={data.name} onChange={(e) => setData('name', e.target.value)} errors={errors.name} />
                    <Input label='Nama Legal' type='text' value={data.legal_name} onChange={(e) => setData('legal_name', e.target.value)} errors={errors.legal_name} />
                    <Input label='NPWP/Tax ID' type='text' value={data.tax_id} onChange={(e) => setData('tax_id', e.target.value)} errors={errors.tax_id} />
                    <div className='grid grid-cols-2 gap-3'>
                        <Input label='Base Currency' type='text' value={data.base_currency_code} onChange={(e) => setData('base_currency_code', e.target.value.toUpperCase())} errors={errors.base_currency_code} />
                        <Input label='Country Code' type='text' value={data.country_code} onChange={(e) => setData('country_code', e.target.value.toUpperCase())} errors={errors.country_code} />
                    </div>
                    <Input label='Timezone' type='text' value={data.timezone} onChange={(e) => setData('timezone', e.target.value)} errors={errors.timezone} />
                    <Input label='Bulan Awal Tahun Fiskal (1-12)' type='number' min={1} max={12} value={data.fiscal_year_start_month} onChange={(e) => setData('fiscal_year_start_month', Number(e.target.value))} errors={errors.fiscal_year_start_month} />

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

            <Table.Card title='Data Company'>
                <Table>
                    <Table.Thead>
                        <tr>
                            <Table.Th>No</Table.Th>
                            <Table.Th>Kode</Table.Th>
                            <Table.Th>Nama</Table.Th>
                            <Table.Th>Currency</Table.Th>
                            <Table.Th>Status</Table.Th>
                            <Table.Th className='w-40'></Table.Th>
                        </tr>
                    </Table.Thead>
                    <Table.Tbody>
                        {companies.data.length ? companies.data.map((company, i) => (
                            <tr key={company.id} className='hover:bg-gray-100 dark:hover:bg-gray-900'>
                                <Table.Td>{i + 1 + ((companies.current_page - 1) * companies.per_page)}</Table.Td>
                                <Table.Td>{company.code}</Table.Td>
                                <Table.Td>{company.name}</Table.Td>
                                <Table.Td>{company.base_currency_code}</Table.Td>
                                <Table.Td>{company.is_active ? 'Aktif' : 'Nonaktif'}</Table.Td>
                                <Table.Td>
                                    <div className='flex gap-2'>
                                        <Button type='modal' variant='orange' icon={<IconPencilCog size={16} strokeWidth={1.5} />} onClick={() => setData({ ...company, isUpdate: true, isOpen: true })} />
                                        <Button type='delete' variant='rose' icon={<IconTrash size={16} strokeWidth={1.5} />} url={route('apps.companies.destroy', company.id)} />
                                    </div>
                                </Table.Td>
                            </tr>
                        )) : (
                            <Table.Empty colSpan={6} message={<><div className='flex justify-center mb-2'><IconDatabaseOff size={24} /></div><span>Data company tidak ditemukan.</span></>} />
                        )}
                    </Table.Tbody>
                </Table>
            </Table.Card>
            {companies.last_page !== 1 && <Pagination links={companies.links} />}
        </>
    );
}

Index.layout = (page) => <AppLayout children={page} />;
