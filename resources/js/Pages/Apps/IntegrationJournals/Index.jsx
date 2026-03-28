import Table from '@/Components/Table';
import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';
import { IconPlaylistAdd, IconPlugConnected } from '@tabler/icons-react';

export default function IntegrationJournalsIndex({ autoJournals }) {
    const currencyFormatter = new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    });

    return (
        <>
            <Head title="Integration Journal" />

            <div className="mb-6 rounded-lg border bg-gradient-to-r from-slate-900 via-slate-800 to-slate-700 p-6 text-white dark:border-gray-800">
                <h1 className="text-2xl font-semibold">Integration Journal Monitor</h1>
                <p className="mt-2 text-sm text-slate-200">
                    Pantau jurnal otomatis yang dihasilkan sistem dari event integrasi.
                </p>
            </div>

            <div className="mt-5 grid grid-cols-1 gap-4">
                <Table.Card title="Auto Journal dari Event Integrasi" icon={<IconPlaylistAdd size={20} strokeWidth={1.5} />}>
                    <Table>
                        <Table.Thead>
                            <tr>
                                <Table.Th>Journal No</Table.Th>
                                <Table.Th>Source</Table.Th>
                                <Table.Th>Event</Table.Th>
                                <Table.Th>Doc No</Table.Th>
                                <Table.Th className="text-right">Nominal</Table.Th>
                                <Table.Th>Status</Table.Th>
                            </tr>
                        </Table.Thead>
                        <Table.Tbody>
                            {autoJournals.length === 0 ? (
                                <Table.Empty colSpan={6} message="Belum ada auto journal yang digenerate dari integrasi." />
                            ) : (
                                autoJournals.map((item) => (
                                    <tr key={item.id} className="hover:bg-gray-100 dark:hover:bg-gray-900">
                                        <Table.Td className="font-medium">{item.journal_no}</Table.Td>
                                        <Table.Td>{item.source_module ?? '-'}</Table.Td>
                                        <Table.Td>
                                            <code className="rounded bg-gray-100 px-2 py-1 text-xs dark:bg-gray-900">{item.source_event ?? '-'}</code>
                                        </Table.Td>
                                        <Table.Td>{item.source_document_no ?? '-'}</Table.Td>
                                        <Table.Td className="text-right">{currencyFormatter.format(item.total_debit ?? 0)}</Table.Td>
                                        <Table.Td>
                                            <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium capitalize text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                                {item.status}
                                            </span>
                                        </Table.Td>
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

IntegrationJournalsIndex.layout = (page) => <AppLayout children={page} icon={<IconPlugConnected size={20} strokeWidth={1.5} />} />;
