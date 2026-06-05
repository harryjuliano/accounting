import AppLayout from '@/Layouts/AppLayout';
import Table from '@/Components/Table';
import Widget from '@/Components/Widget';
import { Head, Link } from '@inertiajs/react';
import {
    IconArrowDownCircle,
    IconArrowUpCircle,
    IconArrowsTransferDown,
    IconBuildingBank,
    IconCash,
    IconClockDollar,
    IconFileAnalytics,
    IconGitMerge,
    IconReceipt,
    IconSettings,
    IconWallet,
} from '@tabler/icons-react';

export default function Index({ page, modules, kpis, statusSummary, integrationSummary, recentTransactions }) {
    const currency = new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    });

    const numberFormat = new Intl.NumberFormat('id-ID');

    const moduleCards = [
        {
            title: 'Cash Receipt',
            subtitle: 'Penerimaan kas/bank',
            href: route('apps.cash-management.page', 'cash-receipts'),
            icon: <IconArrowDownCircle size={20} strokeWidth={1.5} />,
        },
        {
            title: 'Cash Payment',
            subtitle: 'Pembayaran kas/bank',
            href: route('apps.cash-management.page', 'cash-payments'),
            icon: <IconArrowUpCircle size={20} strokeWidth={1.5} />,
        },
        {
            title: 'Bank Transfer',
            subtitle: 'Transfer antar rekening',
            href: route('apps.cash-management.page', 'bank-transfers'),
            icon: <IconArrowsTransferDown size={20} strokeWidth={1.5} />,
        },
        {
            title: 'Cash & Bank Accounts',
            subtitle: 'Master akun kas/bank + COA',
            href: route('apps.cash-management.page', 'accounts'),
            icon: <IconBuildingBank size={20} strokeWidth={1.5} />,
        },
    ];

    const statusRows = ['draft', 'submitted', 'verified', 'approved', 'paid', 'posted', 'reconciled', 'cancelled'];

    return (
        <>
            <Head title={page.title} />

            <div className="mb-6 rounded-lg border bg-gradient-to-r from-blue-950 via-slate-900 to-slate-800 p-6 text-white dark:border-gray-800">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div className="text-xs font-semibold uppercase tracking-[0.25em] text-blue-200">CASH MANAGEMENT</div>
                        <h1 className="mt-2 text-2xl font-semibold">{page.title}</h1>
                        <p className="mt-2 max-w-3xl text-sm text-slate-200">{page.description}</p>
                    </div>
                    <div className="rounded-lg border border-white/10 bg-white/10 px-4 py-3 text-sm">
                        <div className="text-blue-100">Finance Hub Flow</div>
                        <div className="font-semibold">Transaksi → Integration Event → Auto Journal</div>
                    </div>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                <Widget
                    title="Cash/Bank Account"
                    subtitle="Akun aktif"
                    total={numberFormat.format(kpis.active_cash_accounts ?? 0)}
                    icon={<IconWallet size={20} strokeWidth={1.5} />}
                    color="bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300"
                />
                <Widget
                    title="Saldo Cache"
                    subtitle="Operational balance cache"
                    total={currency.format(kpis.cash_balance_cache ?? 0)}
                    icon={<IconCash size={20} strokeWidth={1.5} />}
                    color="bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
                />
                <Widget
                    title="Pending Posting"
                    subtitle="Belum posted/reconciled"
                    total={numberFormat.format(kpis.unposted_transactions ?? 0)}
                    icon={<IconClockDollar size={20} strokeWidth={1.5} />}
                    color="bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300"
                />
                <Widget
                    title="Outstanding Advance"
                    subtitle="Disbursed - settled - returned"
                    total={currency.format(kpis.outstanding_advances ?? 0)}
                    icon={<IconReceipt size={20} strokeWidth={1.5} />}
                    color="bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300"
                />
            </div>

            <div className="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-4">
                {moduleCards.map((item) => (
                    <Link key={item.title} href={item.href} className="rounded-lg border bg-white p-4 transition hover:border-blue-300 hover:shadow-sm dark:border-gray-800 dark:bg-gray-950">
                        <div className="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                            {item.icon}
                        </div>
                        <div className="font-semibold text-gray-900 dark:text-gray-100">{item.title}</div>
                        <div className="mt-1 text-xs text-gray-500">{item.subtitle}</div>
                    </Link>
                ))}
            </div>

            <div className="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
                <Table.Card title="Transaction Status" className="xl:col-span-1">
                    <Table>
                        <Table.Thead>
                            <tr>
                                <Table.Th>Status</Table.Th>
                                <Table.Th className="text-right">Total</Table.Th>
                            </tr>
                        </Table.Thead>
                        <Table.Tbody>
                            {statusRows.map((status) => (
                                <tr key={status} className="hover:bg-gray-50 dark:hover:bg-gray-900">
                                    <Table.Td><span className="capitalize">{status.replace('_', ' ')}</span></Table.Td>
                                    <Table.Td className="text-right font-mono">{numberFormat.format(statusSummary?.[status] ?? 0)}</Table.Td>
                                </tr>
                            ))}
                        </Table.Tbody>
                    </Table>
                </Table.Card>

                <Table.Card title="Cash Management Integration Events" className="xl:col-span-1">
                    <Table>
                        <Table.Thead>
                            <tr>
                                <Table.Th>Status</Table.Th>
                                <Table.Th className="text-right">Total</Table.Th>
                            </tr>
                        </Table.Thead>
                        <Table.Tbody>
                            {['received', 'validated', 'processed', 'failed'].map((status) => (
                                <tr key={status} className="hover:bg-gray-50 dark:hover:bg-gray-900">
                                    <Table.Td><span className="capitalize">{status}</span></Table.Td>
                                    <Table.Td className="text-right font-mono">{numberFormat.format(integrationSummary?.[status] ?? 0)}</Table.Td>
                                </tr>
                            ))}
                        </Table.Tbody>
                    </Table>
                </Table.Card>

                <Table.Card title="Posting Event Catalog" className="xl:col-span-1">
                    <Table>
                        <Table.Thead>
                            <tr>
                                <Table.Th>Module</Table.Th>
                                <Table.Th>Event</Table.Th>
                            </tr>
                        </Table.Thead>
                        <Table.Tbody>
                            {modules.map((item) => (
                                <tr key={item.event} className="hover:bg-gray-50 dark:hover:bg-gray-900">
                                    <Table.Td>
                                        <Link href={item.route} className="font-medium text-blue-600 hover:underline dark:text-blue-300">{item.title}</Link>
                                    </Table.Td>
                                    <Table.Td><code className="rounded bg-gray-100 px-2 py-1 text-xs dark:bg-gray-900">{item.event}</code></Table.Td>
                                </tr>
                            ))}
                        </Table.Tbody>
                    </Table>
                </Table.Card>
            </div>

            <div className="mt-6">
                <Table.Card title="Recent Cash Transactions">
                    <Table>
                        <Table.Thead>
                            <tr>
                                <Table.Th>Document</Table.Th>
                                <Table.Th>Type</Table.Th>
                                <Table.Th>Account</Table.Th>
                                <Table.Th>Date</Table.Th>
                                <Table.Th className="text-right">Amount</Table.Th>
                                <Table.Th>Status</Table.Th>
                            </tr>
                        </Table.Thead>
                        <Table.Tbody>
                            {recentTransactions.length === 0 ? (
                                <Table.Empty colSpan={6} message="Belum ada transaksi Cash Management." />
                            ) : (
                                recentTransactions.map((transaction) => (
                                    <tr key={transaction.document_no} className="hover:bg-gray-50 dark:hover:bg-gray-900">
                                        <Table.Td>{transaction.document_no}</Table.Td>
                                        <Table.Td>{transaction.transaction_type}</Table.Td>
                                        <Table.Td>{transaction.direction === 'transfer' ? `${transaction.account ?? '-'} → ${transaction.target_account ?? '-'}` : (transaction.account ?? '-')}</Table.Td>
                                        <Table.Td>{transaction.transaction_date}</Table.Td>
                                        <Table.Td className="text-right font-mono">{currency.format(transaction.amount ?? 0)}</Table.Td>
                                        <Table.Td><span className="rounded bg-gray-100 px-2 py-1 text-xs capitalize dark:bg-gray-900">{transaction.status}</span></Table.Td>
                                    </tr>
                                ))
                            )}
                        </Table.Tbody>
                    </Table>
                </Table.Card>
            </div>

            <div className="mt-6 grid grid-cols-1 gap-4 md:grid-cols-3">
                <div className="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-950">
                    <div className="mb-2 flex items-center gap-2 font-semibold text-gray-900 dark:text-gray-100"><IconFileAnalytics size={18} /> Reports</div>
                    <p className="text-sm text-gray-500">Cash book, bank book, petty cash report, advance aging, reimbursement, dan reconciliation report disiapkan dari data operasional dan jurnal.</p>
                </div>
                <div className="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-950">
                    <div className="mb-2 flex items-center gap-2 font-semibold text-gray-900 dark:text-gray-100"><IconGitMerge size={18} /> Reconciliation</div>
                    <p className="text-sm text-gray-500">Bank statement matching memakai extension pada bank statement lines yang sudah ada di Accounting.</p>
                </div>
                <div className="rounded-lg border bg-white p-4 dark:border-gray-800 dark:bg-gray-950">
                    <div className="mb-2 flex items-center gap-2 font-semibold text-gray-900 dark:text-gray-100"><IconSettings size={18} /> Setup</div>
                    <p className="text-sm text-gray-500">Payment method, approval matrix, posting preset, dan bank mapping terhubung ke Integration Event dan Posting Rules.</p>
                </div>
            </div>
        </>
    );
}

Index.layout = (page) => <AppLayout children={page} />;
