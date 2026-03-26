import Card from '@/Components/Card';
import Table from '@/Components/Table';
import Widget from '@/Components/Widget';
import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';
import {
    IconArrowUpRight,
    IconBook2,
    IconChecklist,
    IconClockCheck,
    IconFileAnalytics,
    IconLock,
    IconReceiptTax,
    IconReportMoney,
    IconScale,
} from '@tabler/icons-react';

export default function Dashboard({ kpis, statusSummary, periodSummary, topExpenseAccounts, integrationQueue, asOfDate }) {
    const currency = new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    });

    const numberFormat = new Intl.NumberFormat('id-ID');

    const kpiCards = [
        {
            title: 'Total Aset',
            subtitle: 'Posisi Keuangan (s.d bulan berjalan)',
            total: currency.format(kpis.total_asset ?? 0),
            icon: <IconScale size={20} strokeWidth={1.5} />,
        },
        {
            title: 'Laba Bersih YTD',
            subtitle: 'Akumulasi tahun berjalan',
            total: currency.format(kpis.net_profit_ytd ?? 0),
            icon: <IconReportMoney size={20} strokeWidth={1.5} />,
        },
        {
            title: 'Posting Bulan Ini',
            subtitle: `${numberFormat.format(kpis.monthly_posted_entries ?? 0)} jurnal posted`,
            total: currency.format(kpis.monthly_posted_amount ?? 0),
            icon: <IconReceiptTax size={20} strokeWidth={1.5} />,
        },
    ];

    const closingChecklist = [
        {
            task: 'Jurnal Posted (All Time)',
            owner: 'General Ledger',
            status: `${numberFormat.format(statusSummary.posted ?? 0)} jurnal`,
        },
        {
            task: 'Jurnal Menunggu Approval',
            owner: 'Approver',
            status: `${numberFormat.format(statusSummary.pending_approval ?? 0)} jurnal`,
        },
        {
            task: 'Jurnal Draft',
            owner: 'Finance Team',
            status: `${numberFormat.format(statusSummary.draft ?? 0)} jurnal`,
        },
        {
            task: 'Periode Open / Soft Closed',
            owner: 'Controller',
            status: `${numberFormat.format(periodSummary.open ?? 0)} / ${numberFormat.format(periodSummary.soft_closed ?? 0)}`,
        },
    ];

    return (
        <>
            <Head title="Accounting Dashboard" />

            <div className="mb-6 rounded-lg border bg-gradient-to-r from-slate-900 via-slate-800 to-slate-700 p-6 text-white dark:border-gray-800">
                <h1 className="text-2xl font-semibold">Accounting & KPI Dashboard</h1>
                <p className="mt-2 text-sm text-slate-200">
                    KPI di bawah ini dihitung dari data jurnal akuntansi aktual untuk memonitor posisi keuangan,
                    performa laba-rugi, dan disiplin proses closing.
                </p>
                <div className="mt-4 flex flex-wrap gap-2 text-xs">
                    <span className="rounded-full bg-white/15 px-3 py-1">As of: {asOfDate}</span>
                    <span className="rounded-full bg-white/15 px-3 py-1">Period Governance Ready</span>
                    <span className="rounded-full bg-white/15 px-3 py-1">Jurnal & Subledger Monitoring</span>
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
                    <Table.Card title="KPI Proses Closing" icon={<IconChecklist size={20} strokeWidth={1.5} />}>
                        <Table>
                            <Table.Thead>
                                <tr>
                                    <Table.Th>Indikator</Table.Th>
                                    <Table.Th>Owner</Table.Th>
                                    <Table.Th className="text-center">Nilai</Table.Th>
                                </tr>
                            </Table.Thead>
                            <Table.Tbody>
                                {closingChecklist.map((item) => (
                                    <tr key={item.task} className="hover:bg-gray-100 dark:hover:bg-gray-900">
                                        <Table.Td>{item.task}</Table.Td>
                                        <Table.Td>{item.owner}</Table.Td>
                                        <Table.Td className="text-center font-medium">{item.status}</Table.Td>
                                    </tr>
                                ))}
                            </Table.Tbody>
                        </Table>
                    </Table.Card>
                </div>

                <div>
                    <Card
                        title="Ringkasan Neraca"
                        footer={<span className="text-xs text-gray-500">Auto-refresh mengikuti data jurnal</span>}
                    >
                        <div className="space-y-3 text-sm text-gray-700 dark:text-gray-300">
                            <div className="flex items-start justify-between gap-2">
                                <div className="flex items-center gap-2">
                                    <IconBook2 size={18} className="mt-0.5" />
                                    <span>Total Liabilitas</span>
                                </div>
                                <span className="font-medium">{currency.format(kpis.total_liability ?? 0)}</span>
                            </div>
                            <div className="flex items-start justify-between gap-2">
                                <div className="flex items-center gap-2">
                                    <IconLock size={18} className="mt-0.5" />
                                    <span>Total Ekuitas</span>
                                </div>
                                <span className="font-medium">{currency.format(kpis.total_equity ?? 0)}</span>
                            </div>
                            <div className="flex items-start justify-between gap-2">
                                <div className="flex items-center gap-2">
                                    <IconClockCheck size={18} className="mt-0.5" />
                                    <span>Hard Closed Period</span>
                                </div>
                                <span className="font-medium">{numberFormat.format(periodSummary.hard_closed ?? 0)} periode</span>
                            </div>
                            <div className="flex items-start justify-between gap-2">
                                <div className="flex items-center gap-2">
                                    <IconFileAnalytics size={18} className="mt-0.5" />
                                    <span>Audit Closed Period</span>
                                </div>
                                <span className="font-medium">{numberFormat.format(periodSummary.audit_closed ?? 0)} periode</span>
                            </div>
                        </div>
                    </Card>
                </div>
            </div>

            <div className="mt-5 grid grid-cols-1 gap-4 xl:grid-cols-2">
                <Table.Card title="Top Beban Bulan Berjalan" icon={<IconArrowUpRight size={20} strokeWidth={1.5} />}>
                    <Table>
                        <Table.Thead>
                            <tr>
                                <Table.Th>Kode COA</Table.Th>
                                <Table.Th>Akun</Table.Th>
                                <Table.Th className="text-right">Nominal</Table.Th>
                            </tr>
                        </Table.Thead>
                        <Table.Tbody>
                            {topExpenseAccounts.length === 0 ? (
                                <Table.Empty colSpan={3} message="Belum ada transaksi beban di periode ini." />
                            ) : (
                                topExpenseAccounts.map((item) => (
                                    <tr key={`${item.coa_code}-${item.coa_name}`} className="hover:bg-gray-100 dark:hover:bg-gray-900">
                                        <Table.Td>{item.coa_code}</Table.Td>
                                        <Table.Td>{item.coa_name}</Table.Td>
                                        <Table.Td className="text-right font-medium">{currency.format(item.amount)}</Table.Td>
                                    </tr>
                                ))
                            )}
                        </Table.Tbody>
                    </Table>
                </Table.Card>

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
                            {integrationQueue.length === 0 ? (
                                <Table.Empty colSpan={4} message="Belum ada event integrasi pada periode ini." />
                            ) : (
                                integrationQueue.map((item) => (
                                    <tr key={`${item.source}-${item.event}`} className="hover:bg-gray-100 dark:hover:bg-gray-900">
                                        <Table.Td>{item.source}</Table.Td>
                                        <Table.Td>
                                            <code className="rounded bg-gray-100 px-2 py-1 text-xs dark:bg-gray-900">{item.event}</code>
                                        </Table.Td>
                                        <Table.Td className="text-center">{item.posted}</Table.Td>
                                        <Table.Td className="text-center">{item.failed}</Table.Td>
                                    </tr>
                                ))
                            )}
                        </Table.Tbody>
                    </Table>
                </Table.Card>
            </div>
        </>
    );
}

Dashboard.layout = (page) => <AppLayout children={page} />;
