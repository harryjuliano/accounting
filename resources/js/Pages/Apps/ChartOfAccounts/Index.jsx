import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm, usePage } from '@inertiajs/react';
import React from 'react';
import Button from '@/Components/Button';
import Modal from '@/Components/Modal';
import Input from '@/Components/Input';
import Table from '@/Components/Table';
import Search from '@/Components/Search';
import Pagination from '@/Components/Pagination';
import { IconBook2, IconCirclePlus, IconDatabaseOff, IconPencilCheck, IconPencilCog, IconTrash } from '@tabler/icons-react';

export default function Index() {
    const { chartOfAccounts, companies, accountGroups, parentAccounts, errors } = usePage().props;

    const { data, setData, post, transform } = useForm({
        id: '',
        company_id: companies[0]?.id ?? '',
        account_group_id: '',
        parent_id: '',
        code: '',
        name: '',
        alias_name: '',
        level: 1,
        account_type: 'asset',
        normal_balance: 'debit',
        financial_statement_group: 'neraca',
        cashflow_group: '',
        allow_manual_posting: true,
        allow_reconciliation: false,
        requires_dimension: false,
        is_control_account: false,
        is_active: true,
        isUpdate: false,
        isOpen: false,
    });

    transform((formData) => ({
        ...formData,
        account_group_id: formData.account_group_id || null,
        parent_id: formData.parent_id || null,
        _method: formData.isUpdate ? 'put' : 'post',
    }));

    const resetForm = () => {
        setData({
            id: '',
            company_id: companies[0]?.id ?? '',
            account_group_id: '',
            parent_id: '',
            code: '',
            name: '',
            alias_name: '',
            level: 1,
            account_type: 'asset',
            normal_balance: 'debit',
            financial_statement_group: 'neraca',
            cashflow_group: '',
            allow_manual_posting: true,
            allow_reconciliation: false,
            requires_dimension: false,
            is_control_account: false,
            is_active: true,
            isUpdate: false,
            isOpen: false,
        });
    };

    const submit = (e) => {
        e.preventDefault();

        const targetRoute = data.isUpdate ? route('apps.chart-of-accounts.update', data.id) : route('apps.chart-of-accounts.store');
        post(targetRoute, { onSuccess: resetForm });
    };

    return (
        <>
            <Head title='Chart Of Accounts' />
            <div className='mb-2 flex justify-between items-center gap-2'>
                <Button
                    type='button'
                    icon={<IconCirclePlus size={20} strokeWidth={1.5} />}
                    variant='gray'
                    label='Tambah COA'
                    onClick={() => setData('isOpen', true)}
                />
                <div className='w-full md:w-4/12'>
                    <Search url={route('apps.chart-of-accounts.index')} placeholder='Cari akun...' />
                </div>
            </div>

            <Modal show={data.isOpen} onClose={resetForm} title={data.isUpdate ? 'Ubah COA' : 'Tambah COA'} icon={<IconBook2 size={20} strokeWidth={1.5} />}>
                <form onSubmit={submit} className='space-y-4'>
                    <div className='grid grid-cols-2 gap-3'>
                        <div className='flex flex-col gap-2'>
                            <label className='text-gray-600 text-sm'>Company</label>
                            <select className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800' value={data.company_id} onChange={(e) => setData('company_id', Number(e.target.value))}>
                                {companies.map((company) => (
                                    <option key={company.id} value={company.id}>{company.name}</option>
                                ))}
                            </select>
                            {errors.company_id && <small className='text-xs text-red-500'>{errors.company_id}</small>}
                        </div>
                        <div className='flex flex-col gap-2'>
                            <label className='text-gray-600 text-sm'>Parent Account</label>
                            <select className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800' value={data.parent_id} onChange={(e) => setData('parent_id', e.target.value)}>
                                <option value=''>-</option>
                                {parentAccounts.filter((account) => String(account.company_id) === String(data.company_id) && String(account.id) !== String(data.id)).map((account) => (
                                    <option key={account.id} value={account.id}>{account.code} - {account.name}</option>
                                ))}
                            </select>
                            {errors.parent_id && <small className='text-xs text-red-500'>{errors.parent_id}</small>}
                        </div>
                    </div>

                    <div className='grid grid-cols-2 gap-3'>
                        <Input label='Kode Akun' type='text' value={data.code} onChange={(e) => setData('code', e.target.value)} errors={errors.code} />
                        <Input label='Nama Akun' type='text' value={data.name} onChange={(e) => setData('name', e.target.value)} errors={errors.name} />
                    </div>
                    <Input label='Alias' type='text' value={data.alias_name} onChange={(e) => setData('alias_name', e.target.value)} errors={errors.alias_name} />

                    <div className='grid grid-cols-2 gap-3'>
                        <Input label='Level' type='number' min={1} max={10} value={data.level} onChange={(e) => setData('level', Number(e.target.value))} errors={errors.level} />
                        <div className='flex flex-col gap-2'>
                            <label className='text-gray-600 text-sm'>Account Group</label>
                            <select className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800' value={data.account_group_id} onChange={(e) => setData('account_group_id', e.target.value)}>
                                <option value=''>-</option>
                                {accountGroups.filter((group) => String(group.company_id) === String(data.company_id)).map((group) => (
                                    <option key={group.id} value={group.id}>{group.name}</option>
                                ))}
                            </select>
                            {errors.account_group_id && <small className='text-xs text-red-500'>{errors.account_group_id}</small>}
                        </div>
                    </div>

                    <div className='grid grid-cols-2 gap-3'>
                        <div className='flex flex-col gap-2'>
                            <label className='text-gray-600 text-sm'>Account Type</label>
                            <select className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800' value={data.account_type} onChange={(e) => setData('account_type', e.target.value)}>
                                {['asset', 'liability', 'equity', 'revenue', 'cogs', 'expense', 'other_income', 'other_expense'].map((item) => <option key={item} value={item}>{item}</option>)}
                            </select>
                        </div>
                        <div className='flex flex-col gap-2'>
                            <label className='text-gray-600 text-sm'>Normal Balance</label>
                            <select className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800' value={data.normal_balance} onChange={(e) => setData('normal_balance', e.target.value)}>
                                <option value='debit'>Debit</option>
                                <option value='credit'>Credit</option>
                            </select>
                        </div>
                    </div>

                    <div className='grid grid-cols-2 gap-3'>
                        <Input label='Financial Statement Group' type='text' value={data.financial_statement_group} onChange={(e) => setData('financial_statement_group', e.target.value)} errors={errors.financial_statement_group} />
                        <Input label='Cashflow Group' type='text' value={data.cashflow_group} onChange={(e) => setData('cashflow_group', e.target.value)} errors={errors.cashflow_group} />
                    </div>

                    <div className='grid grid-cols-2 gap-3'>
                        <div className='flex flex-col gap-2'>
                            <label className='text-gray-600 text-sm'>Status</label>
                            <select className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800' value={data.is_active ? '1' : '0'} onChange={(e) => setData('is_active', e.target.value === '1')}>
                                <option value='1'>Aktif</option>
                                <option value='0'>Nonaktif</option>
                            </select>
                        </div>
                        <div className='flex flex-col gap-2'>
                            <label className='text-gray-600 text-sm'>Manual Posting</label>
                            <select className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800' value={data.allow_manual_posting ? '1' : '0'} onChange={(e) => setData('allow_manual_posting', e.target.value === '1')}>
                                <option value='1'>Ya</option>
                                <option value='0'>Tidak</option>
                            </select>
                        </div>
                    </div>

                    <Button type='submit' variant='gray' icon={<IconPencilCheck size={20} strokeWidth={1.5} />} label='Simpan' />
                </form>
            </Modal>

            <Table.Card title='Data Chart Of Accounts'>
                <Table>
                    <Table.Thead>
                        <tr>
                            <Table.Th>No</Table.Th>
                            <Table.Th>Company</Table.Th>
                            <Table.Th>Kode</Table.Th>
                            <Table.Th>Nama Akun</Table.Th>
                            <Table.Th>Tipe</Table.Th>
                            <Table.Th>Status</Table.Th>
                            <Table.Th className='w-40'></Table.Th>
                        </tr>
                    </Table.Thead>
                    <Table.Tbody>
                        {chartOfAccounts.data.length ? chartOfAccounts.data.map((account, i) => (
                            <tr key={account.id} className='hover:bg-gray-100 dark:hover:bg-gray-900'>
                                <Table.Td>{i + 1 + ((chartOfAccounts.current_page - 1) * chartOfAccounts.per_page)}</Table.Td>
                                <Table.Td>{account.company?.name}</Table.Td>
                                <Table.Td>{account.code}</Table.Td>
                                <Table.Td>{account.name}</Table.Td>
                                <Table.Td className='capitalize'>{account.account_type}</Table.Td>
                                <Table.Td>{account.is_active ? 'Aktif' : 'Nonaktif'}</Table.Td>
                                <Table.Td>
                                    <div className='flex gap-2'>
                                        <Button type='modal' variant='orange' icon={<IconPencilCog size={16} strokeWidth={1.5} />} onClick={() => setData({ ...account, account_group_id: account.account_group_id ?? '', parent_id: account.parent_id ?? '', isUpdate: true, isOpen: true })} />
                                        <Button type='delete' variant='rose' icon={<IconTrash size={16} strokeWidth={1.5} />} url={route('apps.chart-of-accounts.destroy', account.id)} />
                                    </div>
                                </Table.Td>
                            </tr>
                        )) : (
                            <Table.Empty colSpan={7} message={<><div className='flex justify-center mb-2'><IconDatabaseOff size={24} /></div><span>Data COA tidak ditemukan.</span></>} />
                        )}
                    </Table.Tbody>
                </Table>
            </Table.Card>
            {chartOfAccounts.last_page !== 1 && <Pagination links={chartOfAccounts.links} />}
        </>
    );
}

Index.layout = (page) => <AppLayout children={page} />;
