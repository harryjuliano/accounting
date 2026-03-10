import Card from '@/Components/Card';
import Table from '@/Components/Table';
import Widget from '@/Components/Widget';
import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';
import {
    IconBook2,
    IconChecklist,
    IconClockCheck,
    IconFileAnalytics,
    IconLock,
    IconReceiptTax,
    IconReportMoney,
    IconScale,
} from '@tabler/icons-react';

export default function Dashboard() {
    const kpiCards = [
        {
            title: 'Total Aset',
            subtitle: 'Statement of Financial Position',
            total: 'Rp 125.450.000.000',
            icon: <IconScale size={20} strokeWidth={1.5} />,
        },
        {
            title: 'Laba Bersih YTD',
            subtitle: 'Profit or Loss',
            total: 'Rp 8.240.000.000',
            icon: <IconReportMoney size={20} strokeWidth={1.5} />,
        },
        {
            title: 'Arus Kas Operasi',
            subtitle: 'PSAK 207 / IAS 7',
            total: 'Rp 3.110.000.000',
            icon: <IconReceiptTax size={20} strokeWidth={1.5} />,
        },
    ];

    const closingChecklist = [
        { task: 'Rekonsiliasi AR/AP dengan GL', owner: 'GL Accountant', status: 'Selesai' },
        { task: 'Depresiasi aset tetap bulanan', owner: 'Finance Admin', status: 'Berjalan' },
        { task: 'FX revaluation multi-currency', owner: 'Treasury', status: 'Menunggu Approver' },
        { task: 'Soft close periode Maret 2026', owner: 'Controller', status: 'Belum Dimulai' },
    ];

    const integrationQueue = [
        { source: 'CRM', event: 'sales_invoice_approved', posted: 142, failed: 2 },
        { source: 'Procurement', event: 'vendor_bill_approved', posted: 67, failed: 1 },
        { source: 'Cash Management', event: 'customer_payment_received', posted: 98, failed: 0 },
        { source: 'Payroll', event: 'payroll_posted', posted: 12, failed: 0 },
    ];

    const statusBadge = (status) => {
        const styles = {
            Selesai: 'border-emerald-500/40 bg-emerald-500/10 text-emerald-500',
            Berjalan: 'border-blue-500/40 bg-blue-500/10 text-blue-500',
            'Menunggu Approver': 'border-amber-500/40 bg-amber-500/10 text-amber-500',
            'Belum Dimulai': 'border-rose-500/40 bg-rose-500/10 text-rose-500',
        };

        return (
            <span className={`rounded-full px-2.5 py-1 text-xs font-medium border ${styles[status]}`}>
                {status}
            </span>
        );
    };

    return (
        <>
            <Head title="Accounting Dashboard" />

            <div className="mb-6 rounded-lg border bg-gradient-to-r from-slate-900 via-slate-800 to-slate-700 p-6 text-white dark:border-gray-800">
                <h1 className="text-2xl font-semibold">Accounting & General Ledger Hub</h1>
                <p className="mt-2 text-sm text-slate-200">
                    Single source of truth untuk jurnal, saldo buku besar, period closing, dan pelaporan keuangan
                    sesuai praktik SAK Indonesia (PSAK) dan IFRS.
                </p>
                <div className="mt-4 flex flex-wrap gap-2 text-xs">
                    <span className="rounded-full bg-white/15 px-3 py-1">PSAK 201 - Penyajian Laporan Keuangan</span>
                    <span className="rounded-full bg-white/15 px-3 py-1">PSAK 207 - Laporan Arus Kas</span>
                    <span className="rounded-full bg-white/15 px-3 py-1">Comparative Reporting</span>
                    <span className="rounded-full bg-white/15 px-3 py-1">Period Lock & Audit Trail</span>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                {kpiCards.map((item) => (
                    <Widget
                        key={item.title}
                        title={item.title}
                        subtitle={item.subtitle}
                        color="bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200"
                        icon={item.icon}
                        total={item.total}
                    />
                ))}
            </div>

            <div className="mt-5 grid grid-cols-1 gap-4 xl:grid-cols-3">
                <div className="xl:col-span-2">
                    <Table.Card title="Closing Checklist Bulanan" icon={<IconChecklist size={20} strokeWidth={1.5} />}>
                        <Table>
                            <Table.Thead>
                                <tr>
                                    <Table.Th>Tahapan</Table.Th>
                                    <Table.Th>Owner</Table.Th>
                                    <Table.Th className="text-center">Status</Table.Th>
                                </tr>
                            </Table.Thead>
                            <Table.Tbody>
                                {closingChecklist.map((item) => (
                                    <tr key={item.task} className="hover:bg-gray-100 dark:hover:bg-gray-900">
                                        <Table.Td>{item.task}</Table.Td>
                                        <Table.Td>{item.owner}</Table.Td>
                                        <Table.Td className="text-center">{statusBadge(item.status)}</Table.Td>
                                    </tr>
                                ))}
                            </Table.Tbody>
                        </Table>
                    </Table.Card>
                </div>

                <div>
                    <Card
                        title="Kontrol Governance"
                        footer={<span className="text-xs text-gray-500">Terakhir diperbarui: 10 Mar 2026 09:00 WIB</span>}
                    >
                        <div className="space-y-3 text-sm text-gray-700 dark:text-gray-300">
                            <div className="flex items-start gap-2">
                                <IconLock size={18} className="mt-0.5" />
                                <span>Periode Feb 2026 sudah hard close dan terkunci untuk posting.</span>
                            </div>
                            <div className="flex items-start gap-2">
                                <IconClockCheck size={18} className="mt-0.5" />
                                <span>Seluruh posted journal immutable, koreksi via reversal.</span>
                            </div>
                            <div className="flex items-start gap-2">
                                <IconFileAnalytics size={18} className="mt-0.5" />
                                <span>Audit trail aktif untuk approval, posting, dan perubahan rule.</span>
                            </div>
                            <div className="flex items-start gap-2">
                                <IconBook2 size={18} className="mt-0.5" />
                                <span>Template laporan mendukung Neraca, Laba Rugi, OCI, dan Arus Kas.</span>
                            </div>
                        </div>
                    </Card>
                </div>
            </div>

            <div className="mt-5">
                <Table.Card title="Subledger Integration Queue" icon={<IconFileAnalytics size={20} strokeWidth={1.5} />}>
                    <Table>
                        <Table.Thead>
                            <tr>
                                <Table.Th>Source Module</Table.Th>
                                <Table.Th>Event</Table.Th>
                                <Table.Th className="text-center">Posted</Table.Th>
                                <Table.Th className="text-center">Failed</Table.Th>
                            </tr>
                        </Table.Thead>
                        <Table.Tbody>
                            {integrationQueue.map((item) => (
                                <tr key={`${item.source}-${item.event}`} className="hover:bg-gray-100 dark:hover:bg-gray-900">
                                    <Table.Td>{item.source}</Table.Td>
                                    <Table.Td>
                                        <code className="rounded bg-gray-100 px-2 py-1 text-xs dark:bg-gray-900">{item.event}</code>
                                    </Table.Td>
                                    <Table.Td className="text-center">{item.posted}</Table.Td>
                                    <Table.Td className="text-center">{item.failed}</Table.Td>
                                </tr>
                            ))}
                        </Table.Tbody>
                    </Table>
                </Table.Card>
            </div>
        </>
    );
}

Dashboard.layout = (page) => <AppLayout children={page} />;
