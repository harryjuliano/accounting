import Table from '@/Components/Table';
import Widget from '@/Components/Widget';
import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';
import { IconChecklist, IconFileAnalytics, IconPlugConnected } from '@tabler/icons-react';

export default function IntegrationEventsIndex({ receivedEvents, statusSummary }) {
    const statusCards = [
        { title: 'Received', value: statusSummary.received ?? 0 },
        { title: 'Validated', value: statusSummary.validated ?? 0 },
        { title: 'Processed', value: statusSummary.processed ?? 0 },
        { title: 'Failed', value: statusSummary.failed ?? 0 },
    ];

    return (
        <>
            <Head title="Integration Event" />

            <div className="mb-6 rounded-lg border bg-gradient-to-r from-slate-900 via-slate-800 to-slate-700 p-6 text-white dark:border-gray-800">
                <h1 className="text-2xl font-semibold">Integration Event Monitor</h1>
                <p className="mt-2 text-sm text-slate-200">
                    Pantau seluruh event integrasi yang diterima sistem beserta status prosesnya.
                </p>
            </div>

            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                {statusCards.map((item) => (
                    <Widget
                        key={item.title}
                        title={item.title}
                        subtitle="Jumlah event"
                        total={String(item.value)}
                        icon={<IconChecklist size={20} strokeWidth={1.5} />}
                        color="bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200"
                    />
                ))}
            </div>

            <div className="mt-5 grid grid-cols-1 gap-4">
                <Table.Card title="Daftar Event Integrasi Diterima" icon={<IconFileAnalytics size={20} strokeWidth={1.5} />}>
                    <Table>
                        <Table.Thead>
                            <tr>
                                <Table.Th>ID</Table.Th>
                                <Table.Th>Module</Table.Th>
                                <Table.Th>Event</Table.Th>
                                <Table.Th>Doc</Table.Th>
                                <Table.Th>Processing Status</Table.Th>
                                <Table.Th className="text-center">Open Failure</Table.Th>
                                <Table.Th>Error</Table.Th>
                            </tr>
                        </Table.Thead>
                        <Table.Tbody>
                            {receivedEvents.length === 0 ? (
                                <Table.Empty colSpan={7} message="Belum ada event integrasi yang diterima." />
                            ) : (
                                receivedEvents.map((item) => (
                                    <tr key={item.id} className="hover:bg-gray-100 dark:hover:bg-gray-900">
                                        <Table.Td className="font-medium">#{item.id}</Table.Td>
                                        <Table.Td>{item.source_module}</Table.Td>
                                        <Table.Td>
                                            <code className="rounded bg-gray-100 px-2 py-1 text-xs dark:bg-gray-900">{item.event_name}</code>
                                        </Table.Td>
                                        <Table.Td>{item.source_document_no ?? item.source_document_type ?? '-'}</Table.Td>
                                        <Table.Td>
                                            <span className="rounded-full bg-blue-50 px-3 py-1 text-xs font-medium capitalize text-blue-700 dark:bg-blue-950/40 dark:text-blue-200">
                                                {item.processing_status}
                                            </span>
                                        </Table.Td>
                                        <Table.Td className="text-center">{item.open_failure_count}</Table.Td>
                                        <Table.Td className="max-w-[320px] truncate">{item.error_message ?? '-'}</Table.Td>
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

IntegrationEventsIndex.layout = (page) => <AppLayout children={page} icon={<IconPlugConnected size={20} strokeWidth={1.5} />} />;
