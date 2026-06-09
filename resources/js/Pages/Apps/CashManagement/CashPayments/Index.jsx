import AppLayout from '@/Layouts/AppLayout';
import Button from '@/Components/Button';
import Input from '@/Components/Input';
import Modal from '@/Components/Modal';
import Pagination from '@/Components/Pagination';
import Search from '@/Components/Search';
import Table from '@/Components/Table';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { IconCirclePlus, IconFileTypePdf, IconPencilCheck, IconPencilCog, IconTrash } from '@tabler/icons-react';

const emptyLine = { debit_account_id: '', transaction_code: '', description: '', amount: 0, reference_no: '' };

export default function Index() {
    const { payments, companies, cashAccounts, chartOfAccounts, errors } = usePage().props;
    const currency = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });

    const { data, setData, post, transform } = useForm({
        id: '', company_id: companies[0]?.id ?? '', cash_management_account_id: '', document_no: '', transaction_date: new Date().toISOString().slice(0, 10), posting_date: '',
        counterparty_name: '', description: '', reference_no: '', payment_method: '', lines: [{ ...emptyLine }], isUpdate: false, isOpen: false,
    });

    transform((formData) => ({ ...formData, _method: formData.isUpdate ? 'put' : 'post' }));

    const companyCashAccounts = cashAccounts.filter((account) => Number(account.company_id) === Number(data.company_id));
    const companyCoas = chartOfAccounts.filter((account) => Number(account.company_id) === Number(data.company_id));
    const total = data.lines.reduce((sum, line) => sum + Number(line.amount || 0), 0);

    const resetForm = () => setData({
        id: '', company_id: companies[0]?.id ?? '', cash_management_account_id: '', document_no: '', transaction_date: new Date().toISOString().slice(0, 10), posting_date: '',
        counterparty_name: '', description: '', reference_no: '', payment_method: '', lines: [{ ...emptyLine }], isUpdate: false, isOpen: false,
    });

    const updateLine = (index, field, value) => setData('lines', data.lines.map((line, lineIndex) => lineIndex === index ? { ...line, [field]: value } : line));
    const addLine = () => setData('lines', [...data.lines, { ...emptyLine }]);
    const removeLine = (index) => setData('lines', data.lines.length === 1 ? [{ ...emptyLine }] : data.lines.filter((_, lineIndex) => lineIndex !== index));

    const editPayment = (payment) => setData({
        id: payment.id,
        company_id: payment.company_id,
        cash_management_account_id: payment.cash_management_account_id ?? '',
        document_no: payment.document_no ?? '',
        transaction_date: payment.transaction_date ?? '',
        posting_date: payment.posting_date ?? '',
        counterparty_name: payment.counterparty_name ?? '',
        description: payment.description ?? '',
        reference_no: payment.reference_no ?? '',
        payment_method: payment.payment_method ?? '',
        lines: payment.payment_lines?.length ? payment.payment_lines.map((line) => ({
            debit_account_id: line.debit_account_id,
            transaction_code: line.transaction_code ?? '',
            description: line.description ?? '',
            amount: Number(line.amount ?? 0),
            reference_no: line.reference_no ?? '',
        })) : [{ ...emptyLine }],
        isUpdate: true,
        isOpen: true,
    });

    const submit = (e) => {
        e.preventDefault();
        post(data.isUpdate ? route('apps.cash-management.cash-payments.update', data.id) : route('apps.cash-management.cash-payments.store'), { onSuccess: resetForm });
    };

    return (
        <>
            <Head title="Cash Payment" />
            <div className="mb-4 rounded-lg border bg-white p-5 dark:border-gray-800 dark:bg-gray-950">
                <div className="text-xs font-semibold uppercase tracking-[0.25em] text-blue-600">Cash Management</div>
                <h1 className="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">Cash Payment</h1>
                <p className="mt-2 text-sm text-gray-500">CRUD pembayaran kas/bank, print Payment Voucher, dan generate jurnal otomatis: debit multi-line COA, kredit COA kas/bank.</p>
            </div>

            <div className="mb-2 flex items-center justify-between gap-2">
                <Button type="button" icon={<IconCirclePlus size={20} strokeWidth={1.5} />} variant="gray" label="Tambah Cash Payment" onClick={() => setData('isOpen', true)} />
                <div className="w-full md:w-4/12"><Search url={route('apps.cash-management.cash-payments.index')} placeholder="Cari payment voucher..." /></div>
            </div>

            <Modal show={data.isOpen} onClose={resetForm} maxWidth="6xl" title={data.isUpdate ? 'Ubah Cash Payment' : 'Tambah Cash Payment'}>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid grid-cols-2 gap-3">
                        <div className="flex flex-col gap-2"><label className="text-sm text-gray-600">Company</label><select className="w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" value={data.company_id} onChange={(e) => setData({ ...data, company_id: Number(e.target.value), cash_management_account_id: '', lines: [{ ...emptyLine }] })}>{companies.map((company) => <option key={company.id} value={company.id}>{company.name}</option>)}</select>{errors.company_id && <small className="text-xs text-red-500">{errors.company_id}</small>}</div>
                        <Input label="Nomor Voucher" type="text" value={data.document_no} onChange={(e) => setData('document_no', e.target.value)} errors={errors.document_no} placeholder="Auto jika kosong" />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <Input label="Tanggal" type="date" value={data.transaction_date} onChange={(e) => setData('transaction_date', e.target.value)} errors={errors.transaction_date} />
                        <Input label="Posting Date" type="date" value={data.posting_date} onChange={(e) => setData('posting_date', e.target.value)} errors={errors.posting_date} />
                    </div>
                    <div className="flex flex-col gap-2"><label className="text-sm text-gray-600">Bank / Cash (kredit)</label><select className="w-full rounded-md border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300" value={data.cash_management_account_id} onChange={(e) => setData('cash_management_account_id', e.target.value)}><option value="">Pilih akun kas/bank</option>{companyCashAccounts.map((account) => <option key={account.id} value={account.id}>{account.account_code} - {account.account_name} ({account.account_type}) / COA: {account.gl_account?.code ?? '-'}</option>)}</select>{errors.cash_management_account_id && <small className="text-xs text-red-500">{errors.cash_management_account_id}</small>}</div>
                    <div className="grid grid-cols-2 gap-3"><Input label="Kepada" type="text" value={data.counterparty_name} onChange={(e) => setData('counterparty_name', e.target.value)} errors={errors.counterparty_name} /><Input label="Cheque / Notes No" type="text" value={data.reference_no} onChange={(e) => setData('reference_no', e.target.value)} errors={errors.reference_no} /></div>
                    <Input label="Keterangan" type="text" value={data.description} onChange={(e) => setData('description', e.target.value)} errors={errors.description} />

                    <div className="rounded-lg border dark:border-gray-800">
                        <div className="flex items-center justify-between border-b p-3 dark:border-gray-800"><div className="font-semibold">Detail Debit COA</div><Button type="button" variant="gray" label="Tambah Line" onClick={addLine} /></div>
                        <div className="space-y-3 p-3">
                            {data.lines.map((line, index) => <div key={index} className="grid grid-cols-12 gap-2 rounded border p-2 dark:border-gray-800">
                                <div className="col-span-12 md:col-span-4"><label className="text-xs text-gray-500">COA Debit</label><select className="w-full rounded-md border border-gray-200 bg-white px-2 py-1.5 text-sm dark:border-gray-800 dark:bg-gray-900" value={line.debit_account_id} onChange={(e) => updateLine(index, 'debit_account_id', e.target.value)}><option value="">Pilih COA</option>{companyCoas.map((account) => <option key={account.id} value={account.id}>{account.code} - {account.name}</option>)}</select>{errors[`lines.${index}.debit_account_id`] && <small className="text-xs text-red-500">{errors[`lines.${index}.debit_account_id`]}</small>}</div>
                                <div className="col-span-6 md:col-span-2"><Input label="Kode Transaksi" type="text" value={line.transaction_code} onChange={(e) => updateLine(index, 'transaction_code', e.target.value)} /></div>
                                <div className="col-span-6 md:col-span-2"><Input label="Amount" type="number" min="0" step="0.01" value={line.amount} onChange={(e) => updateLine(index, 'amount', e.target.value)} /></div>
                                <div className="col-span-10 md:col-span-3"><Input label="Reference" type="text" value={line.reference_no} onChange={(e) => updateLine(index, 'reference_no', e.target.value)} /></div>
                                <div className="col-span-2 flex items-end"><Button type="button" variant="rose" label="×" onClick={() => removeLine(index)} /></div>
                                <div className="col-span-12"><Input label="Deskripsi Line" type="text" value={line.description} onChange={(e) => updateLine(index, 'description', e.target.value)} /></div>
                            </div>)}
                        </div>
                        <div className="border-t p-3 text-right font-semibold dark:border-gray-800">Total Kas Keluar: {currency.format(total)}</div>
                    </div>
                    <Button type="submit" variant="gray" icon={<IconPencilCheck size={20} strokeWidth={1.5} />} label="Simpan & Generate Jurnal" />
                </form>
            </Modal>

            <Table.Card title="Data Cash Payment">
                <Table>
                    <Table.Thead><tr><Table.Th>No</Table.Th><Table.Th>Nomor</Table.Th><Table.Th>Tanggal</Table.Th><Table.Th>Bank/Cash</Table.Th><Table.Th>Kepada</Table.Th><Table.Th className="text-right">Total</Table.Th><Table.Th>Journal</Table.Th><Table.Th className="w-48"></Table.Th></tr></Table.Thead>
                    <Table.Tbody>{payments.data.length ? payments.data.map((payment, i) => <tr key={payment.id} className="hover:bg-gray-100 dark:hover:bg-gray-900">
                        <Table.Td>{i + 1 + ((payments.current_page - 1) * payments.per_page)}</Table.Td><Table.Td>{payment.document_no}</Table.Td><Table.Td>{payment.transaction_date}</Table.Td><Table.Td>{payment.cash_account?.account_name}</Table.Td><Table.Td>{payment.counterparty_name}</Table.Td><Table.Td className="text-right font-mono">{currency.format(payment.amount ?? 0)}</Table.Td><Table.Td>{payment.journal_entry?.journal_no ?? '-'}</Table.Td>
                        <Table.Td><div className="flex gap-2"><a className="inline-flex items-center rounded bg-blue-600 px-2 py-1 text-xs text-white" href={route('apps.cash-management.cash-payments.voucher', payment.id)} target="_blank" rel="noreferrer"><IconFileTypePdf size={16} /></a><Button type="modal" variant="orange" icon={<IconPencilCog size={16} strokeWidth={1.5} />} onClick={() => editPayment(payment)} /><Button type="delete" variant="rose" icon={<IconTrash size={16} strokeWidth={1.5} />} url={route('apps.cash-management.cash-payments.destroy', payment.id)} /></div></Table.Td>
                    </tr>) : <Table.Empty colSpan={8} message="Data cash payment tidak ditemukan." />}</Table.Tbody>
                </Table>
            </Table.Card>
            {payments.last_page !== 1 && <Pagination links={payments.links} />}
            <div className="mt-4"><Link href={route('apps.cash-management.index')} className="text-sm text-blue-600 hover:underline">← Kembali ke Cash Management</Link></div>
        </>
    );
}

Index.layout = (page) => <AppLayout children={page} />;
