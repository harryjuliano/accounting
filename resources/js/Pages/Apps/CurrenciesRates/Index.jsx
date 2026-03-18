import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm, usePage } from '@inertiajs/react';
import React from 'react';
import Button from '@/Components/Button';
import Modal from '@/Components/Modal';
import Input from '@/Components/Input';
import Table from '@/Components/Table';
import Search from '@/Components/Search';
import Pagination from '@/Components/Pagination';
import { IconCirclePlus, IconCurrencyDollar, IconDatabaseOff, IconPencilCheck, IconPencilCog, IconTrash } from '@tabler/icons-react';

export default function Index() {
    const { currencies, exchangeRates, companies, currencyOptions, errors } = usePage().props;

    const { data: currencyData, setData: setCurrencyData, post: postCurrency, transform: transformCurrency } = useForm({
        code: '', name: '', symbol: '', decimal_places: 2, is_active: true, isUpdate: false, isOpen: false,
    });

    const { data: rateData, setData: setRateData, post: postRate, transform: transformRate } = useForm({
        id: '', company_id: companies[0]?.id ?? '', rate_date: '', from_currency_code: currencyOptions[0]?.code ?? '',
        to_currency_code: currencyOptions[1]?.code ?? currencyOptions[0]?.code ?? '', rate: 1, rate_type: 'spot', source: '',
        isUpdate: false, isOpen: false,
    });

    transformCurrency((formData) => ({ ...formData, _method: formData.isUpdate ? 'put' : 'post' }));
    transformRate((formData) => ({ ...formData, _method: formData.isUpdate ? 'put' : 'post' }));

    const resetCurrencyForm = () => setCurrencyData({ code: '', name: '', symbol: '', decimal_places: 2, is_active: true, isUpdate: false, isOpen: false });

    const resetRateForm = () => setRateData({
        id: '', company_id: companies[0]?.id ?? '', rate_date: '', from_currency_code: currencyOptions[0]?.code ?? '',
        to_currency_code: currencyOptions[1]?.code ?? currencyOptions[0]?.code ?? '', rate: 1, rate_type: 'spot', source: '',
        isUpdate: false, isOpen: false,
    });

    const submitCurrency = (e) => {
        e.preventDefault();
        postCurrency(currencyData.isUpdate ? route('apps.currencies.update', currencyData.code) : route('apps.currencies.store'), { onSuccess: resetCurrencyForm });
    };

    const submitRate = (e) => {
        e.preventDefault();
        postRate(rateData.isUpdate ? route('apps.exchange-rates.update', rateData.id) : route('apps.exchange-rates.store'), { onSuccess: resetRateForm });
    };

    return (
        <>
            <Head title='Currencies & Rates' />
            <div className='mb-2 flex justify-between items-center gap-2'>
                <div className='flex gap-2'>
                    <Button type='button' icon={<IconCirclePlus size={20} strokeWidth={1.5} />} variant='gray' label='Tambah Currency' onClick={() => setCurrencyData('isOpen', true)} />
                    <Button type='button' icon={<IconCirclePlus size={20} strokeWidth={1.5} />} variant='gray' label='Tambah Exchange Rate' onClick={() => setRateData('isOpen', true)} />
                </div>
                <div className='w-full md:w-4/12'><Search url={route('apps.currencies.index')} placeholder='Cari currency atau rate...' /></div>
            </div>

            <Modal show={currencyData.isOpen} onClose={resetCurrencyForm} title={currencyData.isUpdate ? 'Ubah Currency' : 'Tambah Currency'} icon={<IconCurrencyDollar size={20} strokeWidth={1.5} />}>
                <form onSubmit={submitCurrency} className='space-y-4'>
                    <div className='grid grid-cols-2 gap-3'>
                        <Input label='Kode' type='text' maxLength={3} value={currencyData.code} onChange={(e) => setCurrencyData('code', e.target.value.toUpperCase())} errors={errors.code} />
                        <Input label='Nama' type='text' value={currencyData.name} onChange={(e) => setCurrencyData('name', e.target.value)} errors={errors.name} />
                    </div>
                    <div className='grid grid-cols-2 gap-3'>
                        <Input label='Symbol' type='text' value={currencyData.symbol} onChange={(e) => setCurrencyData('symbol', e.target.value)} errors={errors.symbol} />
                        <Input label='Decimal Places' type='number' min='0' max='6' value={currencyData.decimal_places} onChange={(e) => setCurrencyData('decimal_places', e.target.value)} errors={errors.decimal_places} />
                    </div>
                    <div className='flex flex-col gap-2'>
                        <label className='text-gray-600 text-sm'>Status</label>
                        <select className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800' value={currencyData.is_active ? '1' : '0'} onChange={(e) => setCurrencyData('is_active', e.target.value === '1')}>
                            <option value='1'>Aktif</option>
                            <option value='0'>Nonaktif</option>
                        </select>
                    </div>
                    <Button type='submit' variant='gray' icon={<IconPencilCheck size={20} strokeWidth={1.5} />} label='Simpan' />
                </form>
            </Modal>

            <Modal show={rateData.isOpen} onClose={resetRateForm} title={rateData.isUpdate ? 'Ubah Exchange Rate' : 'Tambah Exchange Rate'} icon={<IconCurrencyDollar size={20} strokeWidth={1.5} />}>
                <form onSubmit={submitRate} className='space-y-4'>
                    <div className='flex flex-col gap-2'>
                        <label className='text-gray-600 text-sm'>Company</label>
                        <select className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800' value={rateData.company_id} onChange={(e) => setRateData('company_id', Number(e.target.value))}>{companies.map((company) => <option key={company.id} value={company.id}>{company.name}</option>)}</select>
                        {errors.company_id && <small className='text-xs text-red-500'>{errors.company_id}</small>}
                    </div>
                    <div className='grid grid-cols-2 gap-3'>
                        <Input label='Tanggal Rate' type='date' value={rateData.rate_date} onChange={(e) => setRateData('rate_date', e.target.value)} errors={errors.rate_date} />
                        <div className='flex flex-col gap-2'>
                            <label className='text-gray-600 text-sm'>Rate Type</label>
                            <select className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800' value={rateData.rate_type} onChange={(e) => setRateData('rate_type', e.target.value)}>{['spot', 'month_end', 'average', 'custom'].map((type) => <option key={type} value={type}>{type}</option>)}</select>
                        </div>
                    </div>
                    <div className='grid grid-cols-2 gap-3'>
                        <div className='flex flex-col gap-2'>
                            <label className='text-gray-600 text-sm'>From Currency</label>
                            <select className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800' value={rateData.from_currency_code} onChange={(e) => setRateData('from_currency_code', e.target.value)}>{currencyOptions.map((currency) => <option key={currency.code} value={currency.code}>{currency.code} - {currency.name}</option>)}</select>
                        </div>
                        <div className='flex flex-col gap-2'>
                            <label className='text-gray-600 text-sm'>To Currency</label>
                            <select className='w-full px-3 py-1.5 border text-sm rounded-md bg-white text-gray-700 dark:bg-gray-900 dark:text-gray-300 border-gray-200 dark:border-gray-800' value={rateData.to_currency_code} onChange={(e) => setRateData('to_currency_code', e.target.value)}>{currencyOptions.map((currency) => <option key={currency.code} value={currency.code}>{currency.code} - {currency.name}</option>)}</select>
                        </div>
                    </div>
                    <div className='grid grid-cols-2 gap-3'>
                        <Input label='Rate' type='number' step='0.0000000001' min='0.0000000001' value={rateData.rate} onChange={(e) => setRateData('rate', e.target.value)} errors={errors.rate} />
                        <Input label='Source' type='text' value={rateData.source} onChange={(e) => setRateData('source', e.target.value)} errors={errors.source} />
                    </div>
                    {errors.composite_unique && <small className='text-xs text-red-500'>{errors.composite_unique}</small>}
                    <Button type='submit' variant='gray' icon={<IconPencilCheck size={20} strokeWidth={1.5} />} label='Simpan' />
                </form>
            </Modal>

            <Table.Card title='Data Currencies'>
                <Table>
                    <Table.Thead><tr><Table.Th>No</Table.Th><Table.Th>Kode</Table.Th><Table.Th>Nama</Table.Th><Table.Th>Symbol</Table.Th><Table.Th>Decimal</Table.Th><Table.Th>Status</Table.Th><Table.Th className='w-40'></Table.Th></tr></Table.Thead>
                    <Table.Tbody>
                        {currencies.data.length ? currencies.data.map((currency, i) => (
                            <tr key={currency.code} className='hover:bg-gray-100 dark:hover:bg-gray-900'>
                                <Table.Td>{i + 1 + ((currencies.current_page - 1) * currencies.per_page)}</Table.Td>
                                <Table.Td>{currency.code}</Table.Td><Table.Td>{currency.name}</Table.Td><Table.Td>{currency.symbol ?? '-'}</Table.Td>
                                <Table.Td>{currency.decimal_places}</Table.Td><Table.Td>{currency.is_active ? 'Aktif' : 'Nonaktif'}</Table.Td>
                                <Table.Td><div className='flex gap-2'><Button type='modal' variant='orange' icon={<IconPencilCog size={16} strokeWidth={1.5} />} onClick={() => setCurrencyData({ ...currency, isUpdate: true, isOpen: true })} /><Button type='delete' variant='rose' icon={<IconTrash size={16} strokeWidth={1.5} />} url={route('apps.currencies.destroy', currency.code)} /></div></Table.Td>
                            </tr>
                        )) : <Table.Empty colSpan={7} message={<><div className='flex justify-center mb-2'><IconDatabaseOff size={24} /></div><span>Data currency tidak ditemukan.</span></>} />}
                    </Table.Tbody>
                </Table>
            </Table.Card>
            {currencies.last_page !== 1 && <Pagination links={currencies.links} />}

            <div className='mt-6'>
                <Table.Card title='Data Exchange Rates'>
                    <Table>
                        <Table.Thead><tr><Table.Th>No</Table.Th><Table.Th>Company</Table.Th><Table.Th>Tanggal</Table.Th><Table.Th>Pair</Table.Th><Table.Th>Rate</Table.Th><Table.Th>Tipe</Table.Th><Table.Th>Source</Table.Th><Table.Th className='w-40'></Table.Th></tr></Table.Thead>
                        <Table.Tbody>
                            {exchangeRates.data.length ? exchangeRates.data.map((rate, i) => (
                                <tr key={rate.id} className='hover:bg-gray-100 dark:hover:bg-gray-900'>
                                    <Table.Td>{i + 1 + ((exchangeRates.current_page - 1) * exchangeRates.per_page)}</Table.Td>
                                    <Table.Td>{rate.company?.name}</Table.Td><Table.Td>{rate.rate_date}</Table.Td><Table.Td>{rate.from_currency_code}/{rate.to_currency_code}</Table.Td>
                                    <Table.Td>{Number(rate.rate)}</Table.Td><Table.Td className='capitalize'>{rate.rate_type}</Table.Td><Table.Td>{rate.source ?? '-'}</Table.Td>
                                    <Table.Td><div className='flex gap-2'><Button type='modal' variant='orange' icon={<IconPencilCog size={16} strokeWidth={1.5} />} onClick={() => setRateData({ ...rate, rate: Number(rate.rate), isUpdate: true, isOpen: true })} /><Button type='delete' variant='rose' icon={<IconTrash size={16} strokeWidth={1.5} />} url={route('apps.exchange-rates.destroy', rate.id)} /></div></Table.Td>
                                </tr>
                            )) : <Table.Empty colSpan={8} message={<><div className='flex justify-center mb-2'><IconDatabaseOff size={24} /></div><span>Data exchange rate tidak ditemukan.</span></>} />}
                        </Table.Tbody>
                    </Table>
                </Table.Card>
                {exchangeRates.last_page !== 1 && <Pagination links={exchangeRates.links} />}
            </div>
        </>
    );
}

Index.layout = (page) => <AppLayout children={page} />;
